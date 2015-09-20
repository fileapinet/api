<?php

namespace FileApi\WorkerBundle\Workers;

use AmyBoyd\PgnParser\Game;
use AmyBoyd\PgnParser\PgnParser;
use Mmoreram\GearmanBundle\Driver\Gearman;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use FileApi\WorkerBundle\Workers\AbstractWorker;

/**
 * @Gearman\Work(
 *     service="file_api_worker.pgn_parser_worker",
 *     defaultMethod = "doBackground"
 * )
 *
 * Parse a PGN file containing chess games.
 *
 * Some data normalization is also performed.
 */
class PgnParserWorker extends AbstractWorker
{
    /**
     * @param  \GearmanJob $job
     * @return boolean
     * @Gearman\Job(name = "parse")
     */
    public function parse(\GearmanJob $job)
    {
        list($workload, $order) = $this->init($job);

        $tmpPgnFile = $this->fileSystem->copyToLocalTemporaryFile($order->getFileSystemPath());

        $games = $this->getNormalizedGamesFromFile($tmpPgnFile);

        $order->addResultAttribute('games', $games);
        $order->addResultAttribute('numberOfGames', count($games));

        $this->dm->persist($order);
        $this->dm->flush();

        $this->logger->log(LogLevel::INFO, 'Parsed PGN file', [
            'orderId' => $order->getId(),
            'numberOfGames' => count($games),
        ]);

        $this->logger->log(LogLevel::INFO, 'Finished', $workload);

        return $job->sendComplete('1');
    }

    private function getNormalizedGamesFromFile($tmpPgnFile)
    {
        $parser = new PgnParser($tmpPgnFile);

        $gamesAsPersistableStructs = array_map(function(Game $game) {
            return json_decode($game->toJSON(), true);
        }, $parser->getGames());

        return $gamesAsPersistableStructs;
    }
}
