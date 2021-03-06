<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\ApiBundle\Document\Order;
use FileApi\ApiBundle\Model\HttpRequest;
use FileApi\WorkerBundle\Workers\ScreenshotWebPageWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

require_once dirname(__DIR__).'/../../../../app/AppKernel.php';

/**
 * Tests for the ScreenshotWebPageWorker.
 */
class ScreenshotWebPageWorkerTest extends BaseUnitTest
{
    protected $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testScreenshot()
    {
        $request = new HttpRequest(new SymfonyRequest());
        $order = new Order(
            $request,
            null,
            null
        );
        $order->addInputAttribute('requestQueryParams', ['url' => 'http://fileapi.dev/']);
        $this->dm->persist($order);
        $this->dm->flush();

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.screenshot_web_page_worker');
        $worker->screenshot($fakeGearmanJob);

        $this->assertArrayHasKey('screenshot', $order->getResult());
    }
}
