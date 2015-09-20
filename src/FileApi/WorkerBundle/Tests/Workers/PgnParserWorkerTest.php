<?php

namespace FileApi\WorkerBundle\Tests\Workers;

use FileApi\ApiBundle\Document\Order;
use FileApi\WorkerBundle\Workers\PgnParserWorker;
use FileApi\Tests\Base\BaseUnitTest;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;

/**
 * Tests for the PgnParserWorker.
 */
class PgnParserWorkerTest extends BaseUnitTest
{
    private $fileSystem;

    public function setUp()
    {
        parent::setUp();
        $this->fileSystem = new FileSystem($this->container->get('partnermarketing_file_system.factory')->build());
    }

    public function testParse()
    {
        $inputFile = $this->fileSystem->writeContent('PgnParserWorkerTest/test.pgn', file_get_contents(__DIR__ . '/test.pgn'));

        $order = $this->getOrder($inputFile);

        $fakeGearmanJob = $this->getMockBuilder('\GearmanJob')->disableOriginalConstructor()->getMock();
        $fakeGearmanJob->expects($this->any())->method('workload')
            ->will($this->returnCallback(function () use ($inputFile, $order) {
                return json_encode([
                    'orderId' => $order->getId(),
                ]);
            }));
        $fakeGearmanJob->expects($this->once())->method('sendComplete');

        $worker = $this->container->get('file_api_worker.pgn_parser_worker');
        $worker->parse($fakeGearmanJob);

        $result = $order->getResult();

        $this->assertEquals(12, $result['numberOfGames']);
        $this->assertCount(12, $result['games']);
        $this->assertArrayHasKey('white', $result['games'][0]);
    }
}
