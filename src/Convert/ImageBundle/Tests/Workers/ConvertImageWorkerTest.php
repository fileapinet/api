<?php

namespace Convert\ImageBundle\Tests\Workers;

use Convert\ImageBundle\Workers\ConvertImageWorker;
use Convert\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__) . '/../../../../app/AppKernel.php';

/**
 * Tests for the ConvertImageWorker.
 */
class ConvertImageWorkerTest extends BaseUnitTest
{
    protected $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testCreateImagesFromJpeg()
    {
        $jpeg = $this->fileSystem->writeContent('ConvertImageWorkerTest/test.jpg', file_get_contents(__DIR__ . '/test.jpg'));

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($jpeg) {
                return json_encode([
                    'fileSystemPath' => $jpeg,
                    'orderId' => 123,
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('convert_image.convert_image_worker');
        $worker->createImages($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles('123/');
        $this->assertCount(10, $filesInFileSystem);
        $this->assertContains('123/image.aai', $filesInFileSystem);
        $this->assertContains('123/image.tiff', $filesInFileSystem);
        $this->assertContains('123/image.webp', $filesInFileSystem);
        $this->assertContains('123/images.zip', $filesInFileSystem);
    }
}
