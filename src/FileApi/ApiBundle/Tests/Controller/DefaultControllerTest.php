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

        $this->gearman->expects($this->once())->method('doNormalJob');

        $response = $this->controller->convertImageToOtherFormatsAction($this->request);
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertMongoId($json['orderId']);
        $this->assertArrayHasKey('result', $json);
    }
}
