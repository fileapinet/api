<?php

namespace Convert\ImageBundle\Tests\Workers;

use Convert\ImageBundle\Workers\ResizeImageDimensionsWorker;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__).'/../../../../app/AppKernel.php';

/**
 * Tests for the ResizeImageDimensionsWorker.
 */
class ResizeImageDimensionsWorkerTest extends \PHPUnit_Framework_TestCase
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

        $worker = $this->container->get('convert_image.resize_image_dimensions_worker');
        $worker->resizeImageDimensions($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles('123/');
        $this->assertContains('123/resized', $filesInFileSystem);

        $actualDimensions = getimagesize($this->fileSystem->getURL('123/resized'));
        $this->assertEquals(150, $actualDimensions[0]);
        $this->assertEquals(160, $actualDimensions[1]);
    }
}
