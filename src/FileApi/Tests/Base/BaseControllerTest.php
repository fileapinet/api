<?php

namespace FileApi\Tests\Base;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class BaseControllerTest extends BaseUnitTest
{
    protected $symfonyRequest;

    protected $dm;

    protected $gearman;

    public function setUp()
    {
        parent::setUp();

        $this->symfonyRequest = new Request();

        $this->container->set('request', $this->symfonyRequest);
        $this->container->get('request_stack')->push($this->symfonyRequest);

        $this->mockGearman();
    }

    /**
     * Clean up of files, Doctrine connections, etc.
     */
    public function tearDown()
    {
        parent::tearDown();

        if (!$this->container) {
            throw new \Exception('There is no container initialized. Try clearing your cache and running the tests again.');
        }

        $this->container->get('doctrine_mongodb')->getConnection()->close();

        $refl = new \ReflectionObject($this);
        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }
    }

    protected function addGETParam($key, $value)
    {
        $this->symfonyRequest->query->set($key, $value);
    }

    protected function addGETParams(array $params)
    {
        $this->symfonyRequest->query->add($params);
    }

    protected function addPOSTParam($key, $value)
    {
        $this->symfonyRequest->request->set($key, $value);
    }

    protected function addPOSTParams(array $params)
    {
        $this->symfonyRequest->request->add($params);
    }

    protected function addFileParam($path, $originalName, $mimeType = 'application/octet-stream',
            $size = null, $error = null, $key = null)
    {
        if (!$size) {
            $size = filesize($path);
        }

        $file = new UploadedFile($path, $originalName, $mimeType, $size, $error);
        $this->symfonyRequest->files->add(["$key"=> $file]);
    }

    private function mockGearman()
    {
        $mock = $this->getMockBuilder('Mmoreram\GearmanBundle\Service\GearmanClient')
            ->disableOriginalConstructor()
            ->setMethods(['doHighJob', 'doBackgroundJob', 'doNormalJob'])
            ->getMock();

        $this->container->set('gearman', $mock);

        $this->gearman = $mock;
    }
}
