<?php

namespace FileApi\ImageBundle\Tests\Workers;

use FileApi\ImageBundle\Workers\ReduceImageFileSizeWorker;
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
        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () {
                return json_encode([
                    'fileSystemPath' => 'file.gif',
                    'orderId' => 123,
                    'targetMaxSizeInBytes' => 35000,
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendFail');

        $worker = $this->container->get('file_api_image.reduce_image_file_size_worker');
        $worker->reduceImageFileSize($fakeGearmanJob);
    }

    private function assertReduceImageFileSize($fileType)
    {
        $inputFile = $this->fileSystem->writeContent('ReduceImageFileSizeWorkerTest/test.' . $fileType, file_get_contents(__DIR__ . '/test.' . $fileType));

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($inputFile) {
                return json_encode([
                    'fileSystemPath' => $inputFile,
                    'orderId' => 123,
                    'targetMaxSizeInBytes' => 35000,
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_image.reduce_image_file_size_worker');
        $worker->reduceImageFileSize($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles('123/');
        $this->assertCount(1, $filesInFileSystem);
        $this->assertContains('123/reduced.' . $fileType, $filesInFileSystem);
        $this->assertLessThan(35000, strlen($this->fileSystem->read('123/reduced.' . $fileType)));
    }
}
