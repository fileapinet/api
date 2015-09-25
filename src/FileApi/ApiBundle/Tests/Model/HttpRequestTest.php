<?php

namespace FileApi\ApiBundle\Tests\Model;

use FileApi\ApiBundle\Model\HttpRequest as FileApiRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class HttpRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testGetHeadersCustomToUs()
    {
        $symfonyRequest = new SymfonyRequest();

        $fileApiRequest = new FileApiRequest($symfonyRequest);
        $this->assertCount(0, $fileApiRequest->getHeadersCustomToUs());

        $symfonyRequest->headers->set('Content-Type', 'application/json');
        $symfonyRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
        $fileApiRequest = new FileApiRequest($symfonyRequest);
        $this->assertCount(0, $fileApiRequest->getHeadersCustomToUs());

        $symfonyRequest->headers->set('X-FileApi-ApiKey', 'abc123');
        $fileApiRequest = new FileApiRequest($symfonyRequest);
        $this->assertCount(1, $fileApiRequest->getHeadersCustomToUs());
    }
}
