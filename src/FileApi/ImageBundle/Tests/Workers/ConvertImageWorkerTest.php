<?php

namespace FileApi\ImageBundle\Tests\Workers;

use FileApi\ImageBundle\Workers\ConvertImageWorker;
use FileApi\Tests\Base\BaseUnitTest;
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

        $order = $this->getOrder($jpeg);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($jpeg, $order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_image.convert_image_worker');
        $worker->createImages($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles($order->getId() . '/');
        $this->assertCount(10, $filesInFileSystem);
        $this->assertContains($order->getId() . '/image.aai', $filesInFileSystem);
        $this->assertContains($order->getId() . '/image.tiff', $filesInFileSystem);
        $this->assertContains($order->getId() . '/image.webp', $filesInFileSystem);
        $this->assertContains($order->getId() . '/images.zip', $filesInFileSystem);
    }
}
