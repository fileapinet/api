<?php

namespace FileApi\ImageBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Partnermarketing\FileSystemBundle\Factory\FileSystemFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * @Gearman\Work(
 *     service="ReduceImageFileSizeWorker",
 *     defaultMethod = "doBackground"
 * )
 */
class ReduceImageFileSizeWorker
{
    protected $dm;

    protected $logger;

    private $fileSystem;

    private $tmpDir;

    public function __construct(ManagerRegistry $mongodb, LoggerInterface $logger,
        FileSystemFactory $fileSystemFactory, $tmpDir)
    {
        $this->dm = $mongodb->getManager();
        $this->logger = $logger;
        $this->fileSystem = new FileSystem($fileSystemFactory->build());
        $this->tmpDir = $tmpDir;
    }

    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "reduceImageFileSize")
     */
    public function reduceImageFileSize(\GearmanJob $job)
    {
        $workload = json_decode($job->workload(), true);

        $this->logger->log(LogLevel::INFO, 'Request received', $workload);

        $fileExtension = strtolower(strrev(explode('.', strrev($workload['fileSystemPath']), 2)[0]));
        if ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
            $this->reduce('jpg', $workload);
            return $job->sendComplete('1');
        } elseif ($fileExtension === 'png') {
            $this->reduce('png', $workload);
            return $job->sendComplete('1');
        } else {
            $this->logger->log(LogLevel::INFO, 'File extension not supported', [
                'extension' => $fileExtension,
                'fileSystemPath' => $workload['fileSystemPath'],
            ]);
            return $job->sendFail();
        }
    }

    private function reduce($fileExtension, $workload)
    {
        $orderId = $workload['orderId'];
        $targetMaxSizeInBytes = $workload['targetMaxSizeInBytes'];
        $originalFile = $this->fileSystem->copyToLocalTemporaryFile($workload['fileSystemPath']);

        if ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
            $command = 'convert %originalFile% -quality %quality% %tmpFile%';
        } elseif ($fileExtension === 'png') {
            $command = 'cat %originalFile% | pngquant --quality %quality% - > %tmpFile%';
        }

        $targetFileSystemPath = $orderId . '/reduced.' . $fileExtension;
        $originalSize = filesize($originalFile);
        $tmpDir = $this->tmpDir . '/ReduceImageFileSizeWorker/';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir);
        }
        $quality = 100;

        do {
            $tmpFile = tempnam($tmpDir, $quality . '-');
            $parameterisedCommand = strtr($command, [
                '%originalFile%' => escapeshellarg($originalFile),
                '%tmpFile%' => escapeshellarg($tmpFile),
                '%quality%' => escapeshellarg($quality),
            ]);
            shell_exec($parameterisedCommand);
            $resultSize = filesize($tmpFile);

            if ($resultSize >= $targetMaxSizeInBytes) {
                unlink($tmpFile);
            }
        } while ($resultSize >= $targetMaxSizeInBytes && $quality >= 0 && $quality--);

        $this->fileSystem->write($targetFileSystemPath, $tmpFile);
        unlink($tmpFile);
        unlink($originalFile);

        $this->logger->log(LogLevel::INFO, 'Reduced image file size', [
            'originalSize' => $originalSize,
            'resultSize' => $resultSize,
            'resultFileSystemPath' => $targetFileSystemPath,
            'orderId' => $orderId,
        ]);
    }
}
