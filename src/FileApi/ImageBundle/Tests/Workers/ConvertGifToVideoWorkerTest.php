<?php

namespace FileApi\ImageBundle\Tests\Workers;

use FileApi\ImageBundle\Workers\ConvertGifToVideoWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__) . '/../../../../app/AppKernel.php';

/**
 * Tests for the ConvertGifToVideoWorker.
 */
class ConvertGifToVideoWorkerTest extends BaseUnitTest
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
    public function testCreateVideos()
    {
        $gif = $this->fileSystem->writeContent('ConvertGifToVideoWorkerTest/test.gif', file_get_contents(__DIR__ . '/test.gif'));

        $order = $this->getOrder($gif);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($gif, $order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_image.convert_gif_to_video_worker');
        $worker->createVideos($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles($order->getId() . '/');
        $this->assertCount(4, $filesInFileSystem);
        $this->assertContains($order->getId() . '/video.webm', $filesInFileSystem);
        $this->assertContains($order->getId() . '/video.mp4', $filesInFileSystem);
        $this->assertContains($order->getId() . '/video.avi', $filesInFileSystem);
        $this->assertContains($order->getId() . '/videos.zip', $filesInFileSystem);
    }
}
