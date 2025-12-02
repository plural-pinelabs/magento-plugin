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
        if (!$order) {
            return;
        }
 
        $payment = $order->getPayment();
        if (!$payment) {
            return;
        }
 
        $method = $payment->getMethod();
        if ($method === 'pinepgpaymentmethod') {
            $order->setState(Order::STATE_NEW);
            $order->setStatus(Order::STATE_PENDING_PAYMENT);
            $order->setCanSendNewEmailFlag(false);
        }
    }
}
 
 