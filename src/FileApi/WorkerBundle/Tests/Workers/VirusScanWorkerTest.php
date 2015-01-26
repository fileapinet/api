<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\WorkerBundle\Workers\VirusScanWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__).'/../../../../app/AppKernel.php';

/**
 * Tests for the VirusScanWorker.
 */
class VirusScanWorkerTest extends BaseUnitTest
{
    protected $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testScan()
    {
        $inputFile = $this->fileSystem->writeContent('VirusScanWorkerTest/test.jpg', file_get_contents(__DIR__ . '/test.jpg'));

        $order = $this->getOrder($inputFile);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($inputFile, $order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.virus_scan_worker');
        $worker->scan($fakeGearmanJob);

        $this->assertTrue($order->getResult()['isVirusFree']);
    }
}
