<?php

namespace FileApi\WorkerBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Psr\Log\LogLevel;
use FileApi\WorkerBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_worker.screenshot_web_page_worker",
 *     description = "Screenshot a web page",
 *     defaultMethod = "doBackground"
 * )
 */
class ScreenshotWebPageWorker extends AbstractWorker
{
    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "screenshot")
     */
    public function screenshot(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $tmpFile = tempnam($this->tmpDir, 'ScreenshotWebPageWorker');
        $command = sprintf('pageres %s %dx%d --crop --filename %s',
            escapeshellarg($order->getInput()['url']),
            '1024',
            '800',
            escapeshellarg($tmpFile));
        $output = shell_exec($command);

        $fileSystemPath = $order->getId() . '/screenshot.png';
        $this->fileSystem->writeContent($fileSystemPath, file_get_contents($tmpFile . '.png'));

        $fileSystemUrl = $this->fileSystem->getURL($fileSystemPath);
        $order->addResultAttribute('screenshot', $fileSystemUrl);

        unlink($tmpFile . '.png');

        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }
}
