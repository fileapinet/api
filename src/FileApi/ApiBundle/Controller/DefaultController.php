<?php

namespace FileApi\ApiBundle\Controller;

use FileApi\ApiBundle\Document\Order;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/convert-gif-to-videos")
     */
    public function convertGifToVideosAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiImageBundleWorkersConvertGifToVideoWorker~createVideos', $order);

        return new JsonResponse($order->getResult());
    }

    /**
     * @return \FileApi\ApiBundle\Document\Order
     */
    private function getOrderFromRequest(Request $request)
    {
        $sourceFileUrl = $request->query->get('source');
        $this->container->get('monolog.logger.request')->log(LogLevel::INFO, 'Source: ' . $sourceFileUrl);

        $extension = strrev(explode('.', strrev($sourceFileUrl), 2)[0]);

        $fsPath = date('Y-m') . '/' . md5($sourceFileUrl) . '.' . $extension;

        $fs = new FileSystem($this->get('partnermarketing_file_system.factory')->build());
        if (!$fs->exists($fsPath)) {
            $fs->writeContent(
                $fsPath,
                file_get_contents($sourceFileUrl)
            );
        }

        $order = new Order($request, $fsPath, $fs->getURL($fsPath));

        $dm = $this->container->get('doctrine_mongodb')->getManager();
        $dm->persist($order);
        $dm->flush();

        return $order;
    }

    private function runWorker($worker, Order $order, array $parameters = [])
    {
        $parameters['orderId'] = $order->getId();

        $this->container->get('monolog.logger.request')->log(LogLevel::INFO, 'Requesting worker ' . $worker);

        $this->container->get('gearman')->doNormalJob($worker, json_encode($parameters));

        $this->container->get('doctrine_mongodb')->getManager()->refresh($order);

        return $order;
    }
}