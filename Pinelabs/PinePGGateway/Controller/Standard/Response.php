<?php

namespace Pinelabs\PinePGGateway\Controller\Standard;

use Pinelabs\PinePGGateway\Controller\PinePGVerify;
use Pinelabs\PinePGGateway\Model\ConfigProvider;
use Pinelabs\PinePGGateway\Model\PinePGPaymentMethod;
use \Magento\Framework\Controller\ResultFactory;

class Response extends \Pinelabs\PinePGGateway\Controller\PinePGAbstract
{
    protected $config;
    protected $pinePGPaymentMethod;
    protected $resultFactory;
    protected $encryptor;
    protected $urlEncoder;
    protected $customer;
    protected $customerSession;
    protected $checkoutSession;

    protected $orderFactory;

    protected $orderSender;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Pinelabs\PinePGGateway\Logger\Logger $logger,
        \Pinelabs\PinePGGateway\Model\PinePGPaymentMethod $paymentMethod,
        \Pinelabs\PinePGGateway\Helper\PinePG $checkoutHelper,
        \Pinelabs\PinePGGateway\Model\ConfigProvider $config,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptorInterface,
        \Magento\Framework\Url\EncoderInterface $encoderInterface,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender  $orderSender
    ) {
        parent::__construct($context, $customerSession, $checkoutSession, $quoteRepository, $orderFactory, $logger, $paymentMethod, $checkoutHelper, $cartManagement, $resultJsonFactory, $filesystem);
        $this->config = $config;
        $this->pinePGPaymentMethod = $paymentMethod;
        $this->resultFactory = $resultFactory;
        $this->encryptor = $encryptorInterface;
        $this->urlEncoder = $encoderInterface;
        $this->customer = $customer;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->orderSender = $orderSender;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $callbackData = $this->getRequest()->getPostValue();


            // Log the callback data for debugging
            $this->_logger->info('PinePG callback data: ' . json_encode($callbackData));

            if (!isset($callbackData['order_id'])) {
                $this->_logger->error('No order_id received in callback data.'. json_encode($callbackData));
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            }

            $paymentMethod = $this->getPaymentMethod();

            $orderId = $callbackData['order_id'];

            $statusEnquiry = $callbackData['status'];
            $maxRetries = 3;
            $retryDelay = 20; // in seconds
            
            if (!empty($orderId)) {
                for ($i = 0; $i < $maxRetries; $i++) {
                    $EnquiryApiResponse = $this->pinePGPaymentMethod->callEnquiryApi($orderId);
                    $statusEnquiry = $EnquiryApiResponse['data']['status'] ?? null;
            
                    $this->_logger->info("Retry #$i - Status from Enquiry API for order_id $orderId: $statusEnquiry");
            
                    if ($statusEnquiry === 'PROCESSED') {
                        break;
                    }
            
                    // Delay before next attempt
                    if ($i < $maxRetries - 1) {
                        sleep($retryDelay); // Wait 15 seconds
                    }
                }
            }
            
            if ($statusEnquiry !== 'PROCESSED') {
                $this->_logger->error("Order is not processed after $maxRetries retries: " . $orderId);
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            }



                $order = $this->orderFactory->create()->load($orderId, 'plural_order_id');

               
                if ($order->getId()) {
                    $entityId =  $order->getId(); // entity_id
                }

           

            if (!$entityId) {
                $this->_logger->error('No matching order found for order_id: ' . $orderId);
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            }

            
          

            if (!$order->getId()) {
                $this->_logger->error('Failed to load order with entity_id: ' . $entityId);
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            }

            // Mark the order as successful
            $payment = $order->getPayment();
            $payment->registerCaptureNotification($order->getGrandTotal());

             $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                
            $order->save();

            $DateTime=date('Y-m-d H:i:s');
            $payment = $order->getPayment();

            $paymentMethod->postProcessing($order, $payment, $callbackData);

            $this->_logger->info('Checkout Session Order ID: ' . $this->checkoutSession->getLastOrderId());


            $this->_logger->info('Order marked as successful. Entity ID: ' . $entityId);

            //add sessions to show success page if session is lost

            if ($order && $order->getId() && $order->getQuoteId() && $order->getIncrementId()) {
                $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                $this->checkoutSession->setLastOrderStatus($order->getStatus());
            } else {
                $this->_logger->error('Missing order data: Cannot set checkout session values.');
            }
                

            //add sessions to show success page if session is lost

            // Optionally send order confirmation email
            try {
                $this->orderSender->send($order);
            } catch (\Exception $e) {
                $this->_logger->critical('Error sending order email: ' . $e->getMessage());
            }

            $resultRedirect->setPath('checkout/onepage/success');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            $this->_logger->error('LocalizedException: ' . $e->getMessage());
            $resultRedirect->setPath('checkout/onepage/failure');
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
            $this->_logger->error('Exception: ' . $e->getMessage());
            $resultRedirect->setPath('checkout/onepage/failure');
        }

        return $resultRedirect;
    }
}
