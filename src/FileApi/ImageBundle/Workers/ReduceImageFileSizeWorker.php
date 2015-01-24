<?php

namespace FileApi\ImageBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\FileBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_image.reduce_image_file_size_worker",
 *     defaultMethod = "doBackground"
 * )
 */
class ReduceImageFileSizeWorker extends AbstractWorker
{
    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "reduceImageFileSize")
     */
    public function reduceImageFileSize(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $fileExtension = strtolower(strrev(explode('.', strrev($order->getFileSystemPath()), 2)[0]));

        if ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
            $this->reduce('jpg', $order, $workload['targetMaxSizeInBytes']);

            return $job->sendComplete('1');
        } elseif ($fileExtension === 'png') {
            $this->reduce('png', $order, $workload['targetMaxSizeInBytes']);

            return $job->sendComplete('1');
        } else {
            $this->logger->log(LogLevel::INFO, 'File extension not supported', [
                'extension' => $fileExtension,
                'fileSystemPath' => $order->getFileSystemPath(),
            ]);

            return $job->sendFail();
        }
    }

    private function reduce($fileExtension, $order, $targetMaxSizeInBytes)
    {
        $originalFile = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());

        if ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
            $command = 'convert %originalFile% -quality %quality% %tmpFile%';
        } elseif ($fileExtension === 'png') {
            $command = 'cat %originalFile% | pngquant --quality %quality% - > %tmpFile%';
        }

        $targetFileSystemPath = $order->getId() . '/reduced.' . $fileExtension;
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
            'orderId' => $order->getId(),
        ]);
    }
}
