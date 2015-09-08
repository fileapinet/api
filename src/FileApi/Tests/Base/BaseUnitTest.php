<?php

namespace FileApi\Tests\Base;

use FileApi\ApiBundle\Document\Order;
use Partnermarketing\FileSystemBundle\ServerFileSystem\ServerFileSystem;

abstract class BaseUnitTest extends \PHPUnit_Framework_TestCase
{
    protected $kernel;
    protected $container;

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
        $request = new \Symfony\Component\HttpFoundation\Request();

        $order = new Order(
            $request,
            $fileSystemPath,
            $this->container->getParameter('partnermarketing_file_system.config')['local_storage']['url'] . '/' . $fileSystemPath
        );

        $this->dm->persist($order);
        $this->dm->flush();

        return $order;
    }
}
