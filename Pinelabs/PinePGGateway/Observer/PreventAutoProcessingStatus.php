<?php
namespace Pinelabs\PinePGGateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class PreventAutoProcessingStatus implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if ($order && $order->getPayment()->getMethod() === 'pinepgpaymentmethod') {
            $order->setState(Order::STATE_NEW);
            $order->setStatus(Order::STATE_NEW);
        }
    }
}
