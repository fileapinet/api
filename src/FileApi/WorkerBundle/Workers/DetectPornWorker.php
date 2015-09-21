<?php

namespace FileApi\WorkerBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Psr\Log\LogLevel;
use FileApi\ApiBundle\Document\Order;
use FileApi\WorkerBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_worker.detect_porn_worker",
 *     description = "Detect porn in an image.",
 *     defaultMethod = "doBackground"
 * )
 */
class DetectPornWorker extends AbstractWorker
{
    const THRESHOLD_SKIN_PERCENTAGE_TO_CONSIDER_IMAGE_AS_PORN = 30;

    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "detectPorn")
     */
    public function detectPorn(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $this->logger->log(LogLevel::INFO, sprintf('Copying to tmp: %s', $order->getFileSystemPath()));

        $tmpFile = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());

        $this->getSkinColorPercent($order, $tmpFile);

        unlink($tmpFile);

        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }

    private function getSkinColorPercent(Order $order, $tmpFile)
    {
        $pythonFile = realpath(__DIR__ . '/../Resources/python/skin-color-detector.py');
        if (!file_exists($pythonFile)) {
            throw new \Exception('skin-color-detector.py does not exist');
        }
        if (!is_executable($pythonFile)) {
            throw new \Exception($pythonFile . ' is not executable');
        }

        $command = "python2 $pythonFile $tmpFile 2>&1";
        $output = shell_exec($command);

        $order->addInternalAttribute('skinColorDetectorOutput', $output);

        if (preg_match('/^\d+(\.\d+)?$/', trim($output)) === 0) {
            // There was an error, so use false to indicate the skin colour percentage could not
            // be calculated. Errors like this must be debugged.
            $order->addInternalAttribute('skinColorPercent', false);
            $order->addResultAttribute('likelihoodOfBeingPorn', 'undetermined');
            $order->addResultAttribute('isPorn', 'undetermined');
            return;
        }

        $skinColorPercent = (float) trim($output);
        $isPorn = $skinColorPercent > self::THRESHOLD_SKIN_PERCENTAGE_TO_CONSIDER_IMAGE_AS_PORN;

        $order->addInternalAttribute('skinColorPercent', $skinColorPercent);
        $order->addResultAttribute('likelihoodOfBeingPorn', round($skinColorPercent));
        $order->addResultAttribute('isPorn', $isPorn);
    }
}
