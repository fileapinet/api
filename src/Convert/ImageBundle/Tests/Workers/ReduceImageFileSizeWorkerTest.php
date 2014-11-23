<?php

namespace Convert\ImageBundle\Tests\Workers;

use Convert\ImageBundle\Workers\ReduceImageFileSizeWorker;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__).'/../../../../app/AppKernel.php';

/**
 * Tests for the ReduceImageFileSizeWorker.
 */
class ReduceImageFileSizeWorkerTest extends \PHPUnit_Framework_TestCase
{
    protected $kernel;
    protected $container;

    public function setUp()
    {
        $this->kernel = new \AppKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function tearDown()
    {
        $this->kernel->shutdown();
        parent::tearDown();
    }

    public function testReduceImageFileSizeFromJpg()
    {
        $this->testReduceImageFileSize('jpg');
    }

    public function testReduceImageFileSizeFromPng()
    {
        $this->testReduceImageFileSize('png');
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

        $worker = $this->container->get('convert_image.reduce_image_file_size_worker');
        $worker->reduceImageFileSize($fakeGearmanJob);
    }

    private function testReduceImageFileSize($fileType)
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

        $worker = $this->container->get('convert_image.reduce_image_file_size_worker');
        $worker->reduceImageFileSize($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles('123/');
        $this->assertContains('123/reduced.' . $fileType, $filesInFileSystem);
        $this->assertLessThan(35000, strlen($this->fileSystem->read('123/reduced.' . $fileType)));
    }
}
