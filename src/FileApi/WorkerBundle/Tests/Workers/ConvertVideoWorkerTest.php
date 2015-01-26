<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\WorkerBundle\Workers\ConvertVideoWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__) . '/../../../../app/AppKernel.php';

/**
 * Tests for the ConvertVideoWorker.
 */
class ConvertVideoWorkerTest extends BaseUnitTest
{
    protected $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testCreateVideosFromJpeg()
    {
        $video = $this->fileSystem->writeContent('ConvertVideoWorkerTest/test.gif', file_get_contents(__DIR__ . '/test.gif'));

        $order = $this->getOrder($video);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($video, $order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.convert_video_worker');
        $worker->createVideos($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles($order->getId() . '/');
        $this->assertCount(5, $filesInFileSystem);
        $this->assertContains($order->getId() . '/video.mp4', $filesInFileSystem);
        $this->assertContains($order->getId() . '/video.webm', $filesInFileSystem);
        $this->assertContains($order->getId() . '/video.flv', $filesInFileSystem);
        $this->assertContains($order->getId() . '/videos.zip', $filesInFileSystem);
    }
}
