<?php

namespace FileApi\ApiBundle\Controller;

use FileApi\ApiBundle\Document\Order;
use Partnermarketing\FileSystemBundle\FileSystem\FileSystem;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FileApi\ApiBundle\View\OrderViewToCustomer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/convert-gif-to-videos")
     * @Method({"GET", "POST"})
     */
    public function convertGifToVideosAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersConvertGifToVideoWorker~createVideos', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/convert-image-to-other-formats")
     * @Method({"GET", "POST"})
     */
    public function convertImageToOtherFormatsAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersConvertImageWorker~createImages', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/reduce-image-file-size")
     * @Method({"GET", "POST"})
     */
    public function reduceImageFileSizeAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersReduceImageFileSizeWorker~reduceImageFileSize', $order, [
            'targetMaxSizeInBytes' => $request->query->get('targetMaxSizeInBytes'),
        ]);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/resize-image")
     * @Method({"GET", "POST"})
     */
    public function resizeImageAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersResizeImageDimensionsWorker~resizeImageDimensions', $order, [
            'targetWidth' => $request->query->get('targetWidth'),
            'targetHeight' => $request->query->get('targetHeight'),
        ]);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/convert-video-to-other-formats")
     * @Method({"GET", "POST"})
     */
    public function convertVideoToOtherFormatsAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersConvertVideoWorker~createVideos', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/convert-ttf-font-to-web-fonts")
     * @Method({"GET", "POST"})
     */
    public function convertTtfFontToWebFontsAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersConvertTtfFontWorker~createWebFonts', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/watermark-image")
     * @Method({"GET", "POST"})
     */
    public function watermarkImageAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersWatermarkImageWorker~watermarkImage', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/virus-scan")
     * @Method({"GET", "POST"})
     */
    public function virusScanAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersVirusScanWorker~scan', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/parse-pgn")
     * @Method({"GET", "POST"})
     */
    public function parsePgnAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersPgnParserWorker~parse', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/detect-porn")
     * @Method({"GET", "POST"})
     */
    public function detectPornAction(Request $request)
    {
        $order = $this->getOrderFromRequest($request);
        $order = $this->runWorker('FileApiWorkerBundleWorkersDetectPornWorker~detectPorn', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @Route("/screenshot")
     * @Method({"GET", "POST"})
     */
    public function screenshotWebPageAction(Request $request)
    {
        $order = new Order($request, null, null);

        $dm = $this->container->get('doctrine_mongodb')->getManager();
        $dm->persist($order);
        $dm->flush();

        $order = $this->runWorker('FileApiWorkerBundleWorkersScreenshotWebPageWorker~screenshot', $order);

        return new OrderViewToCustomer($order);
    }

    /**
     * @return \FileApi\ApiBundle\Document\Order
     */
    private function getOrderFromRequest(Request $request)
    {
        if ($request->query->has('source')) {
            return $this->createOrderFromRequestWithSourceUrl($request, $request->query->get('source'));
        } else if ($request->request->has('source')) {
            return $this->createOrderFromRequestWithSourceUrl($request, $request->request->get('source'));
        } else if ($request->files->has('source')) {
            return $this->createOrderFromRequestWithUploadedFile($request, $request->files->get('source'));
        } else {
            throw new \Exception('@todo - error.');
        }
    }

    /**
     * @return \FileApi\ApiBundle\Document\Order
     */
    private function createOrderFromRequestWithSourceUrl(Request $request, $sourceFileUrl)
    {
        $this->container->get('monolog.logger.request')
            ->log(LogLevel::INFO, 'Source: ' . $sourceFileUrl);

        $fileExtension = strrev(explode('.', strrev($sourceFileUrl), 2)[0]);

        $fsPath = sprintf('sources/%s/%s/%s.%s',
            date('Y-m'),
            date('d'),
            md5($sourceFileUrl),
            $fileExtension
        );

        return $this->createOrderFromFileSystemPathAndContent($request, $fsPath, file_get_contents($sourceFileUrl));
    }

    /**
     * @return \FileApi\ApiBundle\Document\Order
     */
    private function createOrderFromRequestWithUploadedFile(Request $request, UploadedFile $file)
    {
        $this->container->get('monolog.logger.request')
            ->log(LogLevel::INFO, 'File uploaded: ' . $file->getClientOriginalName());

        $fsPath = sprintf('sources/%s/%s/%s.%s',
            date('Y-m'),
            date('d'),
            md5(file_get_contents($file->getRealPath())),
            $file->guessExtension()
        );

        return $this->createOrderFromFileSystemPathAndContent($request, $fsPath, file_get_contents($file->getRealPath()));
    }

    /**
     * @return \FileApi\ApiBundle\Document\Order
     */
    private function createOrderFromFileSystemPathAndContent(Request $request, $fsPath, $content)
    {
        $fs = new FileSystem($this->get('partnermarketing_file_system.factory')->build());

        if (!$fs->exists($fsPath)) {
            $fs->writeContent($fsPath, $content);
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
