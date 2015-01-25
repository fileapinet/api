<?php

namespace FileApi\WorkerBundle\Workers;

use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Partnermarketing\FileSystemBundle\Factory\FileSystemFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bridge\Doctrine\ManagerRegistry;

class AbstractWorker
{
    protected $dm;

    protected $logger;

    protected $fileSystem;

    protected $tmpDir;

    public function __construct(ManagerRegistry $mongodb, LoggerInterface $logger,
        FileSystemFactory $fileSystemFactory, $tmpDir)
    {
        $this->dm = $mongodb->getManager();
        $this->logger = $logger;
        $this->fileSystem = new FileSystem($fileSystemFactory->build());
        $this->tmpDir = $tmpDir;
    }

    public function init(\GearmanJob $job)
    {
        $workload = json_decode($job->workload(), true);
        $this->logger->log(LogLevel::INFO, 'Request received', $workload);

        return [
            $workload,
            $this->getOrder($workload),
        ];
    }

    protected function getOrder(array $workload)
    {
        return $this->dm->find('FileApi\ApiBundle\Document\Order', $workload['orderId']);
    }
}
