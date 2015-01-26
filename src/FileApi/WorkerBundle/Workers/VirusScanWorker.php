<?php

namespace FileApi\WorkerBundle\Workers;

use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\WorkerBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_worker.virus_scan_worker",
 *     defaultMethod = "doBackground"
 * )
 */
class VirusScanWorker extends AbstractWorker
{
    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "scan")
     */
    public function scan(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $file = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());
        $output = `clamscan -i --no-summary $file`;
        $isVirusFree = empty($output);
        $order->addResultAttribute('isVirusFree', $isVirusFree);

        $this->dm->persist($order);
        $this->dm->flush();

        return $job->sendComplete('1');
    }
}
