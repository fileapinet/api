<?php

namespace FileApi\WorkerBundle\Workers;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use FileApi\ApiBundle\Document\Order;

class WebsiteMistakesService
{
    private $dm;

    private $logger;

    public function __construct(ManagerRegistry $mongodb, LoggerInterface $logger)
    {
        $this->dm = $mongodb->getManager();
        $this->logger = $logger;
    }

    public function checkUrl(Order $order)
    {
        exec(
            '/home/fileapi/project/api/current/vendor/twogether/sweet-qa/sweet-qa test ' . escapeshellarg($order->getInput()['requestQueryParams']['url']),
            $sweetQaOutput,
            $sweetQaReturnCode
        );

        $isOK = $sweetQaReturnCode === 0;

        $order->addResultAttribute('isOK', $isOK);
        $order->addResultAttribute('details', $sweetQaOutput);
        $order->addInternalAttribute('sweetQaOutput', implode("\n", $sweetQaOutput));
        $order->addInternalAttribute('sweetQaReturnCode', $sweetQaReturnCode);

        $this->dm->persist($order);
        $this->dm->flush();
    }
}
