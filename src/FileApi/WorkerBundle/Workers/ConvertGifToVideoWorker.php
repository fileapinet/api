<?php

namespace FileApi\WorkerBundle\Workers;

use ZipArchive;
use FileApi\ApiBundle\Document\Order;
use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\WorkerBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_image.convert_gif_to_video_worker",
 *     defaultMethod = "doBackground"
 * )
 */
class ConvertGifToVideoWorker extends AbstractWorker
{
    private static $targetFormatExtensions = [
        'webm',
        'avi',
        'mp4',
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

        $zipFile = tempnam($this->tmpDir, 'ConvertGifToVideoWorker') . '.zip';
        $zipArchive = new ZipArchive();
        $zipArchive->open($zipFile, ZipArchive::CREATE);

        foreach (self::$targetFormatExtensions as $targetFormatExtension) {
            $this->convertFileToFormat($tmpFile, $targetFormatExtension, $zipArchive, $order);
        }

        $this->saveZipToFileSystem($zipArchive, $zipFile, $order);

        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }

    private function convertFileToFormat($file, $targetFormatExtension, ZipArchive $zipArchive, Order $order)
    {
        $targetFile = tempnam($this->tmpDir, 'ConvertGifToVideoWorker') . '.' . $targetFormatExtension;
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

    private function saveZipToFileSystem(ZipArchive $zipArchive, $zipFile, Order $order)
    {
        $zipArchive->close();
        $fileSystemPath = $order->getId() . '/videos.zip';
        $this->fileSystem->write($fileSystemPath, $zipFile);

        $order->addResultAttribute('allVideosInAZip', $this->fileSystem->getURL($fileSystemPath));
    }
}
