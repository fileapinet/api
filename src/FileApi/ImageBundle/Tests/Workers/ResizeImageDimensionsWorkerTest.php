<?php

namespace FileApi\ImageBundle\Tests\Workers;

use FileApi\ImageBundle\Workers\ResizeImageDimensionsWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__).'/../../../../app/AppKernel.php';

/**
 * Tests for the ResizeImageDimensionsWorker.
 */
class ResizeImageDimensionsWorkerTest extends BaseUnitTest
{
    protected $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testResizeImageDimensions()
    {
        $inputFile = $this->fileSystem->writeContent('ResizeImageDimensionsWorkerTest/test.jpg', file_get_contents(__DIR__ . '/test.jpg'));

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($inputFile) {
                return json_encode([
                    'fileSystemPath' => $inputFile,
                    'orderId' => 123,
                    'targetWidth' => 150,
                    'targetHeight' => 160,
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_image.resize_image_dimensions_worker');
        $worker->resizeImageDimensions($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles('123/');
        $this->assertCount(1, $filesInFileSystem);
        $this->assertContains('123/resized', $filesInFileSystem);

        $actualDimensions = getimagesize($this->fileSystem->getURL('123/resized'));
        $this->assertEquals(150, $actualDimensions[0]);
        $this->assertEquals(160, $actualDimensions[1]);
    }
}
