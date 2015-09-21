<?php

namespace FileApi\ApiBundle\View;

use FileApi\ApiBundle\Document\Order;
use Symfony\Component\HttpFoundation\JsonResponse;

class OrderViewToCustomer extends JsonResponse
{
    public function __construct(Order $order)
    {
        parent::__construct($this->jsonSerializeOrder($order));
    }

    private function jsonSerializeOrder(Order $order)
    {
        return [
            'orderId' => $order->getId(),
            'result' => $order->getResult(),
        ];
    }
}
