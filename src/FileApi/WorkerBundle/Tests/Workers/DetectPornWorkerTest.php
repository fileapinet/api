<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

/**
 * Tests for the DetectPornWorker.
 */
class DetectPornWorkerTest extends BaseUnitTest
{
    protected $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testDetectPorn_withNotPornImage()
    {
        $jpeg = $this->fileSystem->writeContent(
            'DetectPornWorkerTest/test-not-porn.jpg',
            file_get_contents(__DIR__ . '/test-not-porn.jpg')
        );

        $order = $this->getOrder($jpeg);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.detect_porn_worker');
        $worker->detectPorn($fakeGearmanJob);

        $this->assertFalse($order->getResult()['isPorn']);
    }

    public function testDetectPorn_withPornImage()
    {
        $jpeg = $this->fileSystem->writeContent(
            'DetectPornWorkerTest/test-is-porn.jpg',
            file_get_contents(__DIR__ . '/test-is-porn.jpg')
        );

        $order = $this->getOrder($jpeg);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.detect_porn_worker');
        $worker->detectPorn($fakeGearmanJob);

        $this->assertTrue($order->getResult()['isPorn']);
    }
}
