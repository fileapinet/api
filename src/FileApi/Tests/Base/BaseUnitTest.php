<?php

namespace FileApi\Tests\Base;

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
    }

    public function tearDown()
    {
        $this->kernel->shutdown();

        // Clean the local file system's folder.
        $fileSystemConfig = $this->container->getParameter('partnermarketing_file_system.config');
        $localFileSystemPath = $fileSystemConfig['local_storage']['path'];
        if ($localFileSystemPath && is_dir($localFileSystemPath)) {
            // Do an ultra-paranoid safety check to ensure the entire local server file system
            // doesn't get deleted.
            if (strpos($localFileSystemPath, realpath($this->kernel->getRootDir() . '/../')) !== false) {
                ServerFileSystem::deleteFilesInDirectoryRecursively($localFileSystemPath);
            }
        }
    }
}
