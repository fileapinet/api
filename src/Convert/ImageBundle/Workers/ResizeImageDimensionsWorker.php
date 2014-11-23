<?php

namespace Convert\ImageBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Partnermarketing\FileSystemBundle\Factory\FileSystemFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * @Gearman\Work(
 *     service="ResizeImageDimensionsWorker",
 *     defaultMethod = "doBackground"
 * )
 */
class ResizeImageDimensionsWorker
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
     * @Gearman\Job(name = "resizeImageDimensions")
     */
    public function resizeImageDimensions(\GearmanJob $job)
    {
        $workload = json_decode($job->workload(), true);

        $this->logger->log(LogLevel::INFO, 'Request received', $workload);

        $orderId = $workload['orderId'];
        $targetWidth = $workload['targetWidth'];
        $targetHeight = $workload['targetHeight'];
        $originalFile = $this->fileSystem->copyToLocalTemporaryFile($workload['fileSystemPath']);
        $tmpFile = tempnam($this->tmpDir, 'ResizeImageDimensionsWorker');

        $command = 'convert -resize %targetWidth%x%targetHeight%! %originalFile% %tmpFile%';
        $parameterisedCommand = strtr($command, [
            '%originalFile%' => escapeshellarg($originalFile),
            '%tmpFile%' => escapeshellarg($tmpFile),
            '%targetWidth%' => escapeshellarg($targetWidth),
            '%targetHeight%' => escapeshellarg($targetHeight),
        ]);
        shell_exec($parameterisedCommand);

        $targetFileSystemPath = $orderId . '/resized';
        $this->fileSystem->write($targetFileSystemPath, $tmpFile);

        unlink($tmpFile);
        unlink($originalFile);

        $this->logger->log(LogLevel::INFO, 'Resized image dimensions', [
            'targetWidth' => $targetWidth,
            'targetHeight' => $targetHeight,
            'resultFileSystemPath' => $targetFileSystemPath,
            'orderId' => $orderId,
        ]);

        return $job->sendComplete('1');
    }
}
