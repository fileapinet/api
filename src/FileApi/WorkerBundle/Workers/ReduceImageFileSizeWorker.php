<?php

namespace FileApi\WorkerBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\WorkerBundle\Workers\AbstractWorker;

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
            $tmpFile = tempnam($tmpDir, $quality . '-') . '.' . $fileExtension;
            $parameterisedCommand = strtr($command, [
                '%originalFile%' => escapeshellarg($originalFile),
                '%tmpFile%' => escapeshellarg($tmpFile),
                '%quality%' => escapeshellarg($quality),
            ]);
            shell_exec($parameterisedCommand);
            if (!$tmpFile) {
                throw new \Exception('File not created: ' . $tmpFile);
            }
            $resultSize = filesize($tmpFile);

            if ($resultSize > $targetMaxSizeInBytes) {
                unlink($tmpFile);
            }
        } while ($resultSize > $targetMaxSizeInBytes && $quality > 1 && $quality--);

        if ($resultSize > $targetMaxSizeInBytes) {
            $this->logger->log(LogLevel::INFO, 'Failed to reduce image file size', [
                'originalSize' => $originalSize,
                'orderId' => $order->getId(),
            ]);
            $order->addResultAttribute('error', 'Unable to get this file below the target max size. Only managed to get to: ' . $resultSize);
            $this->dm->persist($order);
            $this->dm->flush();
            return;
        }

        $this->fileSystem->writeContent($targetFileSystemPath, file_get_contents($tmpFile));
        unlink($tmpFile);
        unlink($originalFile);

        $order->addResultAttribute('newFile', $this->fileSystem->getURL($targetFileSystemPath));
        $order->addResultAttribute('newSize', $resultSize);
        $order->addResultAttribute('originalSize', $originalSize);
        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Reduced image file size', [
            'originalSize' => $originalSize,
            'resultSize' => $resultSize,
            'resultFileSystemPath' => $targetFileSystemPath,
            'orderId' => $order->getId(),
        ]);
    }
}
