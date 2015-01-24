<?php

namespace Convert\ImageBundle\Tests\Workers;

use Convert\ImageBundle\Workers\ConvertGifToVideoWorker;
use Convert\Tests\Base\BaseUnitTest;
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

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($gif) {
                return json_encode([
                    'fileSystemPath' => $gif,
                    'orderId' => 123,
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('convert_image.convert_gif_to_video_worker');
        $worker->createVideos($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles('123/');
        $this->assertCount(4, $filesInFileSystem);
        $this->assertContains('123/video.webm', $filesInFileSystem);
        $this->assertContains('123/video.mp4', $filesInFileSystem);
        $this->assertContains('123/video.avi', $filesInFileSystem);
        $this->assertContains('123/videos.zip', $filesInFileSystem);
    }
}
