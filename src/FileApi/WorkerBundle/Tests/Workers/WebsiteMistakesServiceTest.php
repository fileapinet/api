<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\ApiBundle\Document\Order;
use FileApi\ApiBundle\Model\HttpRequest;
use FileApi\WorkerBundle\Workers\WebsiteMistakesService;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Tests for the WebsiteMistakesService.
 */
class WebsiteMistakesServiceTest extends BaseUnitTest
{
    public function testCheckUrl()
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

        $service = $this->container->get('file_api_worker.website_mistakes_service');
        $service->checkUrl($order);

        $this->assertFalse($order->getResult()['isOK']);
    }
}
