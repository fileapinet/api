<?php

namespace FileApi\WorkerBundle\Workers;

use ZipArchive;
use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\WorkerBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_worker.convert_video_worker",
 *     defaultMethod = "doBackground"
 * )
 */
class ConvertVideoWorker extends AbstractWorker
{
    private static $targetFormatExtensions = [
        'mp4',
        'webm',
        'flv',
        'avi',
    ];

    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "createVideos")
     */
    public function createVideos(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $tmpFile = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());

        $zipFile = tempnam($this->tmpDir, 'ConvertVideoWorker') . '.zip';
        $zipArchive = new ZipArchive();
        $zipArchive->open($zipFile, ZipArchive::CREATE);

        foreach (self::$targetFormatExtensions as $targetFormatExtension) {
            $this->convertFileToFormat($tmpFile, $targetFormatExtension, $order, $zipArchive);
        }

        $this->saveZipToFileSystem($zipArchive, $zipFile, $order);

        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }

    private function convertFileToFormat($file, $targetFormatExtension, $order, ZipArchive $zipArchive)
    {
        $targetFile = tempnam($this->tmpDir, 'ConvertVideoWorker') . '.' . $targetFormatExtension;
        `ffmpeg -i $file $targetFile 2>&1 > /dev/null`;
        $fileSystemPath = $order->getId() . '/video.' . $targetFormatExtension;
        $this->fileSystem->write($fileSystemPath, $targetFile);

        $this->logger->log(LogLevel::INFO, 'Created video file', [
            'format' => $targetFormatExtension,
            'fileSystemPath' => $fileSystemPath
        ]);

        $zipArchive->addFile($targetFile, 'video.' . $targetFormatExtension);

        $order->addResultAttribute($targetFormatExtension, $this->fileSystem->getURL($fileSystemPath));
    }

    private function saveZipToFileSystem(ZipArchive $zipArchive, $zipFile, $order)
    {
        $zipArchive->close();
        $fileSystemPath = $order->getId() . '/videos.zip';
        $this->fileSystem->write($fileSystemPath, $zipFile);

        $order->addResultAttribute('allVideosInAZip', $this->fileSystem->getURL($fileSystemPath));
    }
}
