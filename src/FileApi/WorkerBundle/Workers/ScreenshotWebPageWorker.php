<?php

namespace FileApi\WorkerBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Psr\Log\LogLevel;

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

        $tmpFile = tempnam($this->tmpDir, 'ScreenshotWebPageWorker') . '.png';
        $command = sprintf('node ' . __DIR__ . '/../Resources/node/screenshot.js %s %s',
            escapeshellarg($order->getInput()['requestQueryParams']['url']),
            escapeshellarg($tmpFile));
        $output = shell_exec($command);

        if (!file_exists($tmpFile)) {
            throw new \Exception('File not created by webshot: ' . $tmpFile);
        }
        if (filesize($tmpFile) === 0) {
            throw new \Exception('File created by webshot has size 0: ' . $tmpFile);
        }

        $fileSystemPath = $order->getId() . '/screenshot.png';
        $this->fileSystem->writeContent($fileSystemPath, file_get_contents($tmpFile));

        unlink($tmpFile);

        $fileSystemUrl = $this->fileSystem->getURL($fileSystemPath);
        $order->addResultAttribute('screenshot', $fileSystemUrl);

        $order->addInternalAttribute('screenshotJsOutput', $output);

        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }
}
