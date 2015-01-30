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
        $command = sprintf('pageres [ %s %s ] --crop --filename %s',
            escapeshellarg($order->getInput()['url']),
            escapeshellarg('1024x800'),
            escapeshellarg($tmpFile));
        $output = shell_exec($command);

        $this->logger->log(LogLevel::INFO, 'Command', [
            'command' => $command,
            'output' => $output
        ]);

        $fileSystemPath = $order->getId() . '/screenshot.png';
        $this->fileSystem->writeContent($fileSystemPath, file_get_contents($tmpFile . '.png'));

        unlink($tmpFile . '.png');

        $fileSystemUrl = $this->fileSystem->getURL($fileSystemPath);
        $order->addResultAttribute('screenshot', $fileSystemUrl);


        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }
}
