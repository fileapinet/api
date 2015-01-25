<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\WorkerBundle\Workers\ConvertTtfFontWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

require_once dirname(__DIR__) . '/../../../../app/AppKernel.php';

/**
 * Tests for the ConvertTtfFontWorker.
 */
class ConvertTtfFontWorkerTest extends BaseUnitTest
{
    protected $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testCreateWebFonts()
    {
        $ttf = $this->fileSystem->writeContent('ConvertTtfFontWorkerTest/test.ttf', file_get_contents(__DIR__ . '/test.ttf'));

        $order = $this->getOrder($ttf);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.convert_ttf_font_worker');
        $worker->createWebFonts($fakeGearmanJob);

        $filesInFileSystem = $this->fileSystem->getFiles($order->getId() . '/');
        $this->assertCount(4, $filesInFileSystem);
        $this->assertContains($order->getId() . '/test.eot', $filesInFileSystem);
        $this->assertContains($order->getId() . '/test.svg', $filesInFileSystem);
        $this->assertContains($order->getId() . '/test.woff', $filesInFileSystem);
        $this->assertContains($order->getId() . '/test.otf', $filesInFileSystem);
    }
}
