<?php

namespace FileApi\Tests\Base;

use FileApi\ApiBundle\Document\Order;
use FileApi\ApiBundle\Model\HttpRequest as FileApiRequest;
use Partnermarketing\FileSystemBundle\ServerFileSystem\ServerFileSystem;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

abstract class BaseUnitTest extends \PHPUnit_Framework_TestCase
{
    protected $kernel;

    protected $container;

    protected $dm;

    public function setUp()
    {
        $this->kernel = new \AppKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
        $this->dm = $this->container->get('doctrine_mongodb')->getManager();
    }

    public function tearDown()
    {
        $this->kernel->shutdown();

        $this->cleanLocalFileSystem();
    }

    /**
     * Clean the local file system's folder.
     */
    private function cleanLocalFileSystem()
    {
        if (!$this->container) {
            return;
        }

        $fileSystemConfig = $this->container->getParameter('partnermarketing_file_system.config');
        $localFileSystemPath = $fileSystemConfig['local_storage']['path'];
        if (!$localFileSystemPath || !is_dir($localFileSystemPath)) {
            return;
        }

        // Do an ultra-paranoid safety check to ensure the entire local server's file system
        // (instead of the application file system) doesn't get deleted.
        if (strpos($localFileSystemPath, realpath($this->kernel->getRootDir() . '/../')) !== false) {
            ServerFileSystem::deleteFilesInDirectoryRecursively($localFileSystemPath);
        }
    }

    protected function getOrder($fileSystemPath)
    {
        $request = new FileApiRequest(new SymfonyRequest());

        $order = new Order(
            $request,
            $fileSystemPath,
            $this->container->getParameter('partnermarketing_file_system.config')['local_storage']['url'] . '/' . $fileSystemPath
        );

        $this->dm->persist($order);
        $this->dm->flush();

        return $order;
    }

    protected function assertFileSystemFileExists($urlOrPath)
    {
        $fs = $this->container->get('partnermarketing_file_system.factory')->build();
        $this->assertTrue($fs->exists($urlOrPath), 'File exists: ' . $urlOrPath);
    }

    protected function putFileInFileSystem($path, $content)
    {
        $fs = $this->container->get('partnermarketing_file_system.factory')->build();
        $fs->writeContent($path, $content);
    }

    protected function getFileSystemFileByPathRegex($pathRegex)
    {
        $filesInFileSystem = $this->getFilesInFileSystem();

        foreach ($filesInFileSystem as $file) {
            if (preg_match($pathRegex, $file)) {
                return $file;
            }
        }
    }

    protected function getFileInFileSystemContent($path)
    {
        $fs = $this->container->get('partnermarketing_file_system.factory')->build();
        return $fs->read($path);
    }

    /**
     * Reports an error if the given value is not a MongoDB ID as a string.
     */
    protected function assertMongoId($value)
    {
        $this->assertInternalType('string', $value);
        $this->assertRegExp('/^[a-f0-9]{24}$/', $value);
    }
}
