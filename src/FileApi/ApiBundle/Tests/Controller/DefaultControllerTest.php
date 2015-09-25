<?php

namespace FileApi\ApiBundle\Tests\Controller;

use FileApi\ApiBundle\Controller\DefaultController;
use FileApi\Tests\Base\BaseControllerTest;

class DefaultControllerTest extends BaseControllerTest
{
    private $controller;

    public function setUp()
    {
        parent::setUp();

        $this->controller = new DefaultController();
        $this->controller->setContainer($this->container);
    }

    public function testAnyActionUsingSourceUrlInQueryString()
    {
        $this->addGETParam('source', 'http://api.fileapi.dev/fixtures/burgers.jpg');
        $this->addHeader('X-FileApi-ApiKey', 'abc123');

        $this->gearman->expects($this->once())->method('doNormalJob');

        $response = $this->controller->convertImageToOtherFormatsAction($this->symfonyRequest);
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertMongoId($json['orderId']);
        $this->assertArrayHasKey('result', $json);
    }

    public function testAnyActionUsingSourceUrlInPOSTBody()
    {
        $this->addPOSTParam('source', 'http://api.fileapi.dev/fixtures/burgers.jpg');

        $this->gearman->expects($this->once())->method('doNormalJob');

        $response = $this->controller->convertImageToOtherFormatsAction($this->symfonyRequest);
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertMongoId($json['orderId']);
        $this->assertArrayHasKey('result', $json);
    }

    public function testAnyActionUsingFileUpload()
    {
        $file = __DIR__ . '/burgers.jpg';
        $this->addFileParam($file, 'burgers.jpg', 'image/jpeg', null, null, 'source');

        $this->gearman->expects($this->once())->method('doNormalJob');

        $response = $this->controller->convertImageToOtherFormatsAction($this->symfonyRequest);
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertMongoId($json['orderId']);
        $this->assertArrayHasKey('result', $json);
    }
}
