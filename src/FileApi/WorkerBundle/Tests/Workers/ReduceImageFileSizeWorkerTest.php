<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\WorkerBundle\Workers\ReduceImageFileSizeWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__).'/../../../../app/AppKernel.php';

/**
 * Tests for the ReduceImageFileSizeWorker.
 */
class ReduceImageFileSizeWorkerTest extends BaseUnitTest
{
    protected $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    /**
     * @group slow
     */
    public function testReduceImageFileSizeFromJpg()
    {
        $this->assertReduceImageFileSize('jpg');
    }

    /**
     * @group slow
     */
    public function testReduceImageFileSizeFromPng()
    {
        $this->assertReduceImageFileSize('png');
    }

    public function testReduceImageFileSizeFromUnsupportedFileExtension()
    {
        $inputFile = $this->fileSystem->writeContent('ReduceImageFileSizeWorkerTest/test.doc', file_get_contents(__DIR__ . '/test.doc'));

        $order = $this->getOrder($inputFile);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendFail');

        $worker = $this->container->get('file_api_image.reduce_image_file_size_worker');
        $worker->reduceImageFileSize($fakeGearmanJob);
    }

    private function assertReduceImageFileSize($fileType)
    {
        $inputFile = $this->fileSystem->writeContent('ReduceImageFileSizeWorkerTest/test.' . $fileType, file_get_contents(__DIR__ . '/test.' . $fileType));

        $order = $this->getOrder($inputFile);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($inputFile, $order) {
                return json_encode([
                    'orderId' => $order->getId(),
                    'targetMaxSizeInBytes' => 35000,
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_image.reduce_image_file_size_worker');
        $worker->reduceImageFileSize($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles($order->getId() . '/');
        $this->assertCount(1, $filesInFileSystem);
        $this->assertContains($order->getId() . '/reduced.' . $fileType, $filesInFileSystem);
        $this->assertLessThan(35000, strlen($this->fileSystem->read($order->getId() . '/reduced.' . $fileType)));
    }
}
