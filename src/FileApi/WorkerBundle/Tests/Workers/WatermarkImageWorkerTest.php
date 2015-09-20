<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\ApiBundle\Document\Order;
use FileApi\WorkerBundle\Workers\WatermarkImageWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

/**
 * Tests for the WatermarkImageWorker.
 */
class WatermarkImageWorkerTest extends BaseUnitTest
{
    private $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testWatermarkImage_jpgOverlayedWithJpgInSouthEast()
    {
        $inputFile = $this->fileSystem->writeContent('WatermarkImageWorkerTest/test.jpg', file_get_contents(__DIR__ . '/test.jpg'));

        $order = $this->getOrder($inputFile);
        $order->addInputAttribute('requestQueryParams', [
            'watermark' => 'http://api.fileapi.dev/fixtures/brain.jpg',
            'corner' => 'southeast',
        ]);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($inputFile, $order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.watermark_image_worker');
        $worker->watermarkImage($fakeGearmanJob);

        $this->assertWatermarkedFileExists($order);
    }

    public function testWatermarkImage_largeJpgOverlayedWithJpgInCenter()
    {
        $inputFile = $this->fileSystem->writeContent(
            'WatermarkImageWorkerTest/burgers.jpg',
            file_get_contents('http://api.fileapi.dev/fixtures/burgers.jpg')
        );

        $order = $this->getOrder($inputFile);
        $order->addInputAttribute('requestQueryParams', [
            'watermark' => 'http://api.fileapi.dev/fixtures/brain.jpg',
            'corner' => 'center',
        ]);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($inputFile, $order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.watermark_image_worker');
        $worker->watermarkImage($fakeGearmanJob);

        $this->assertWatermarkedFileExists($order);
    }

    private function assertWatermarkedFileExists(Order $order)
    {
        $this->assertArrayHasKey('watermarked', $order->getResult());

        $filesInFileSystem = $this->fileSystem->getFiles($order->getId() . '/');
        $this->assertCount(1, $filesInFileSystem);
        $this->assertContains($order->getId() . '/watermarked.jpg', $filesInFileSystem);
    }
}
