<?php

namespace FileApi\ImageBundle\Workers;

use ZipArchive;
use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\FileBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_image.convert_image_worker",
 *     defaultMethod = "doBackground"
 * )
 */
class ConvertImageWorker extends AbstractWorker
{
    private static $targetFormatExtensions = [
        'aai',
        'bmp',
        'gif',
        'jpg',
        'pdf',
        'png',
        'raw',
        'tiff',
        'webp',
    ];

    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "createImages")
     */
    public function createImages(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $tmpFile = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());

        $zipFile = tempnam($this->tmpDir, 'ConvertImageWorker') . '.zip';
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
        $targetFile = tempnam($this->tmpDir, 'ConvertImageWorker') . '.' . $targetFormatExtension;
        `convert $file $targetFile`;
        $fileSystemPath = $order->getId() . '/image.' . $targetFormatExtension;
        $this->fileSystem->write($fileSystemPath, $targetFile);

        $this->logger->log(LogLevel::INFO, 'Created image file', [
            'format' => $targetFormatExtension,
            'fileSystemPath' => $fileSystemPath
        ]);

        $zipArchive->addFile($targetFile, 'image.' . $targetFormatExtension);

        $order->addResultAttribute($targetFormatExtension, $this->fileSystem->getURL($fileSystemPath));
    }

    private function saveZipToFileSystem(ZipArchive $zipArchive, $zipFile, $order)
    {
        $zipArchive->close();
        $fileSystemPath = $order->getId() . '/images.zip';
        $this->fileSystem->write($fileSystemPath, $zipFile);

        $order->addResultAttribute('allImagesInAZip', $this->fileSystem->getURL($fileSystemPath));
    }
}
