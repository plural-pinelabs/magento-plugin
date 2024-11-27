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

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Psr\Log\LoggerInterface $logger,
        \Pinelabs\PinePGGateway\Model\PinePGPaymentMethod $paymentMethod,
        \Pinelabs\PinePGGateway\Helper\PinePG $checkoutHelper,
        \Pinelabs\PinePGGateway\Model\ConfigProvider $config,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptorInterface,
        \Magento\Framework\Url\EncoderInterface $encoderInterface,
        \Magento\Framework\Filesystem $filesystem
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
    }

    public function execute()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/PinePG/' . date("Y-m-d") . '.log');
        $this->logger = new \Zend_Log();
        $this->logger->addWriter($writer);
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $callbackData = $this->getRequest()->getPostValue();

            // Log the callback data for debugging
            $this->logger->info('PinePG callback data: ' . json_encode($callbackData));

            if (!isset($callbackData['order_id'])) {
                $this->logger->err('No order_id received in callback data.');
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            }

            $orderId = $callbackData['order_id'];

            // Retrieve the entity_id using the plural_order_id (order_id from callback)
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();

            $tableName = $resource->getTableName('sales_order');

            $query = "SELECT entity_id FROM $tableName WHERE plural_order_id = :order_id LIMIT 1";
            $entityId = $connection->fetchOne($query, ['order_id' => $orderId]);

            if (!$entityId) {
                $this->logger->err('No matching order found for order_id: ' . $orderId);
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            }

            // Load the order using entity_id
            $order = $objectManager->create('Magento\Sales\Model\Order')->load($entityId);

            if (!$order->getId()) {
                $this->logger->err('Failed to load order with entity_id: ' . $entityId);
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            }

            // Mark the order as successful
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);

            $order->save();

            $this->logger->info('Checkout Session Order ID: ' . $this->checkoutSession->getLastOrderId());


            $this->logger->info('Order marked as successful. Entity ID: ' . $entityId);

            // Optionally send order confirmation email
            try {
                $orderSender = $objectManager->create('Magento\Sales\Model\Order\Email\Sender\OrderSender');
                $orderSender->send($order);
            } catch (\Exception $e) {
                $this->logger->critical('Error sending order email: ' . $e->getMessage());
            }

            $resultRedirect->setPath('checkout/onepage/success');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            $this->logger->err('LocalizedException: ' . $e->getMessage());
            $resultRedirect->setPath('checkout/onepage/failure');
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
            $this->logger->err('Exception: ' . $e->getMessage());
            $resultRedirect->setPath('checkout/onepage/failure');
        }

        return $resultRedirect;
    }
}
