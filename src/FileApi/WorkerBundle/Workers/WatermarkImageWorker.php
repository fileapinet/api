<?php

namespace FileApi\WorkerBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\WorkerBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_worker.watermark_image_worker",
 *     defaultMethod = "doBackground"
 * )
 *
 * Watermark an image.
 *
 * The order's input argument `requestQueryParams` must have these arguments:
 * - watermark - the URL to a watermark file.
 * - corner - can be southeast, center, north, etc.
 */
class WatermarkImageWorker extends AbstractWorker
{
    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "watermarkImage")
     */
    public function watermarkImage(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $tmpCopyOfOriginal = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());
        $watermarkUrl = $order->getInputAttribute('requestQueryParams')['watermark'];
        $corner = $order->getInputAttribute('requestQueryParams')['corner'];

        $fileExtension = strtolower(strrev(explode('.', strrev($order->getFileSystemPath()), 2)[0]));

        $tmpWatermarkedFile = $this->addWatermarkToFile($tmpCopyOfOriginal, $watermarkUrl, $corner, $fileExtension);

        $saveTo = $order->getId() . '/watermarked.' . $fileExtension;
        $this->fileSystem->write($saveTo, $tmpWatermarkedFile);

        $urlSavedTo = $this->fileSystem->getURL($saveTo);
        $order->addResultAttribute('watermarked', $urlSavedTo);

        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Created watermarked image file', [
            'fileSystemPath' => $saveTo,
            'orderId' => $order->getId(),
        ]);

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        unlink($tmpWatermarkedFile);
        unlink($tmpCopyOfOriginal);

        return $job->sendComplete('1');
    }

    private function addWatermarkToFile($tmpCopyOfOriginal, $watermarkUrl, $corner, $fileExtensionOfOriginal)
    {
        $tmpFileForOutput = tempnam($this->tmpDir, 'WatermarkImageWorker') . '.' . $fileExtensionOfOriginal;

        // Usage of composite, according to it's `man` page:
        // composite [ options ... ] change-file base-file [ mask-file ] output-image
        // `change-file` is the watermark image.
        // `base-file` is the original image.
        // `output-image` is the destination to write the watermarked image to.
        $command = sprintf('composite -compose atop -gravity %s %s %s %s',
            escapeshellarg($corner),
            escapeshellarg($watermarkUrl),
            escapeshellarg($tmpCopyOfOriginal),
            escapeshellarg($tmpFileForOutput)
        );
        shell_exec($command);

        return $tmpFileForOutput;
    }
}
