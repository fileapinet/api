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
 *     service="ConvertImageWorker",
 *     defaultMethod = "doBackground"
 * )
 */
class ConvertImageWorker
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
     * @Gearman\Job(name = "createImages")
     */
    public function createImages(\GearmanJob $job)
    {
        $workload = json_decode($job->workload(), true);

        $this->logger->log(LogLevel::INFO, 'Request received', $workload);

        $orderId = $workload['orderId'];
        $tmpFile = $this->fileSystem->copyToLocalTemporaryFile($workload['fileSystemPath']);

        $this->convertFileToFormat($tmpFile, 'aai', $orderId);
        $this->convertFileToFormat($tmpFile, 'bmp', $orderId);
        $this->convertFileToFormat($tmpFile, 'gif', $orderId);
        $this->convertFileToFormat($tmpFile, 'jpg', $orderId);
        $this->convertFileToFormat($tmpFile, 'pdf', $orderId);
        $this->convertFileToFormat($tmpFile, 'png', $orderId);
        $this->convertFileToFormat($tmpFile, 'raw', $orderId);
        $this->convertFileToFormat($tmpFile, 'tiff', $orderId);
        $this->convertFileToFormat($tmpFile, 'webp', $orderId);

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }

    private function convertFileToFormat($file, $targetFormatExtension, $orderId)
    {
        $targetFile = tempnam($this->tmpDir, 'ConvertImageWorker') . '.' . $targetFormatExtension;
        `convert $file $targetFile`;
        $fileSystemPath = $orderId . '/image.' . $targetFormatExtension;
        $this->fileSystem->write($fileSystemPath, $targetFile);

        $this->logger->log(LogLevel::INFO, 'Created image file', [
            'format' => $targetFormatExtension,
            'fileSystemPath' => $fileSystemPath
        ]);
    }
}
