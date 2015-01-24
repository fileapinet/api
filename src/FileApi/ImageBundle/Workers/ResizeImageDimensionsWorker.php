<?php

namespace FileApi\ImageBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\FileBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_image.resize_image_dimensions_worker",
 *     defaultMethod = "doBackground"
 * )
 */
class ResizeImageDimensionsWorker extends AbstractWorker
{
    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "resizeImageDimensions")
     */
    public function resizeImageDimensions(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $targetWidth = (int) $workload['targetWidth'];
        $targetHeight = (int) $workload['targetHeight'];
        $originalFile = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());
        $tmpFile = tempnam($this->tmpDir, 'ResizeImageDimensionsWorker');

        $command = 'convert -resize %targetWidth%x%targetHeight%! %originalFile% %tmpFile%';
        $parameterisedCommand = strtr($command, [
            '%originalFile%' => escapeshellarg($originalFile),
            '%tmpFile%' => escapeshellarg($tmpFile),
            '%targetWidth%' => escapeshellarg($targetWidth),
            '%targetHeight%' => escapeshellarg($targetHeight),
        ]);
        shell_exec($parameterisedCommand);

        $targetFileSystemPath = $order->getId() . '/resized';
        $this->fileSystem->write($targetFileSystemPath, $tmpFile);

        unlink($tmpFile);
        unlink($originalFile);

        $order->addResultAttribute('resizedImage', $this->fileSystem->getURL($targetFileSystemPath));
        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Resized image dimensions', [
            'targetWidth' => $targetWidth,
            'targetHeight' => $targetHeight,
            'resultFileSystemPath' => $targetFileSystemPath,
            'orderId' => $order->getId(),
        ]);

        return $job->sendComplete('1');
    }
}
