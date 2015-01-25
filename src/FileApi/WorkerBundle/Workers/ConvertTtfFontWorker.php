<?php

namespace FileApi\WorkerBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Psr\Log\LogLevel;
use FileApi\WorkerBundle\Workers\AbstractWorker;

/**
 * This worker creates web font files from a TTF font file.
 *
 * It uses a bash script called `bin/create-web-fonts`, which in turn uses a tool called 'fontforge'.
 *
 * The fonts created are in these formats:
 * - .eot
 * - .svg
 * - .otf
 * - .woff
 *
 * @Gearman\Work(
 *     service="file_api_worker.convert_ttf_font_worker",
 *     description = "Create web font files from a TTF font file.",
 *     defaultMethod = "doBackground"
 * )
 */
class ConvertTtfFontWorker extends AbstractWorker
{
    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "createWebFonts")
     */
    public function createWebFonts(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $this->logger->log(LogLevel::INFO, sprintf('Copying to tmp: %s', $order->getFileSystemPath()));

        $tmpFile = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());
        rename($tmpFile, $tmpFile . '.ttf');
        $tmpFile = $tmpFile . '.ttf';
        $ttfFileName = strrev(explode('/', strrev(preg_replace('/\.ttf$/', '', $order->getFileSystemPath())), 2)[0]);

        $bashFile = __DIR__ . '/../Resources/bash/create-web-fonts';
        if (!file_exists($bashFile)) {
            throw new \Exception('create-web-fonts does not exist');
        }
        if (!is_executable($bashFile)) {
            throw new \Exception(realpath($bashFile) . ' is not executable');
        }

        $output = `$bashFile $tmpFile 2>&1`;
        foreach (['eot', 'svg', 'woff', 'otf'] as $extension) {
            $generatedFile = preg_replace('/\.ttf$/', '', $tmpFile) . '.' . $extension;

            $this->logger->log(LogLevel::INFO, sprintf('Generated %s. File size %d bytes.', $generatedFile, filesize($generatedFile)));

            $fileSystemPath = $order->getId() . '/' .$ttfFileName . '.' . $extension;
            $this->fileSystem->writeContent($fileSystemPath, file_get_contents($generatedFile));

            $fileSystemUrl = $this->fileSystem->getURL($fileSystemPath);
            $order->addResultAttribute($extension, $fileSystemUrl);

            unlink($generatedFile);
        }

        unlink($tmpFile);

        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }
}
