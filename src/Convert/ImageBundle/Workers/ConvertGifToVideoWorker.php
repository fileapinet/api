<?php

namespace Convert\ImageBundle\Workers;

use ZipArchive;
use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Partnermarketing\FileSystemBundle\Factory\FileSystemFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * @Gearman\Work(
 *     service="ConvertGifToVideoWorker",
 *     defaultMethod = "doBackground"
 * )
 */
class ConvertGifToVideoWorker
{
    protected $dm;

    protected $logger;

    private $fileSystem;

    private $tmpDir;

    private static $targetFormatExtensions = [
        'webm',
        'avi',
        'mp4',
    ];

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
     * @Gearman\Job(name = "createVideos")
     */
    public function createVideos(\GearmanJob $job)
    {
        $workload = json_decode($job->workload(), true);

        $this->logger->log(LogLevel::INFO, 'Request received', $workload);

        $orderId = $workload['orderId'];
        $tmpFile = $this->fileSystem->copyToLocalTemporaryFile($workload['fileSystemPath']);

        $zipFile = tempnam($this->tmpDir, 'ConvertGifToVideoWorker') . '.zip';
        $zipArchive = new ZipArchive();
        $zipArchive->open($zipFile, ZipArchive::CREATE);

        foreach (self::$targetFormatExtensions as $targetFormatExtension) {
            $this->convertFileToFormat($tmpFile, $targetFormatExtension, $orderId, $zipArchive);
        }

        $this->saveZipToFileSystem($zipArchive, $zipFile, $orderId);

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }

    private function convertFileToFormat($file, $targetFormatExtension, $orderId, ZipArchive $zipArchive)
    {
        $targetFile = tempnam($this->tmpDir, 'ConvertGifToVideoWorker') . '.' . $targetFormatExtension;
        `ffmpeg -i $file $targetFile 2>&1 > /dev/null`;
        $fileSystemPath = $orderId . '/video.' . $targetFormatExtension;
        $this->fileSystem->write($fileSystemPath, $targetFile);

        $this->logger->log(LogLevel::INFO, 'Created video file', [
            'format' => $targetFormatExtension,
            'fileSystemPath' => $fileSystemPath
        ]);

        $zipArchive->addFile($targetFile, 'video.' . $targetFormatExtension);
    }

    private function saveZipToFileSystem(ZipArchive $zipArchive, $zipFile, $orderId)
    {
        $zipArchive->close();
        $fileSystemPath = $orderId . '/videos.zip';
        $this->fileSystem->write($fileSystemPath, $zipFile);
    }
}
