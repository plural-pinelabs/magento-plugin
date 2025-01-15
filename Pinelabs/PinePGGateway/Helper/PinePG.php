<?php

namespace Pinelabs\PinePGGateway\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;



class PinePG extends AbstractHelper
{
    protected $session;
    protected $quote;
    protected $quoteManagement;
    protected $transactionBuilder;
 

    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        ScopeConfigInterface $scopeConfig,
        BuilderInterface $transactionBuilder
    ) {
        $this->session = $session;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
        $this->transactionBuilder = $transactionBuilder;
        parent::__construct($context);

        $logPath = BP . '/var/log/PinePG/' . date("Y-m-d") . '.log';
        $writer = new \Zend_Log_Writer_Stream($logPath);
        $this->logger = new \Zend_Log();
        $this->logger->addWriter($writer);
    }

    public function cancelCurrentOrder($comment)
    {
        $order = $this->session->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            return true;
        }
        return false;
    }

    public function restoreQuote()
    {
        return $this->session->restoreQuote();
    }

    public function getUrl($route, $params = [])
    {
        return $this->_getUrl($route, $params);
    }

    

    public function processRefund($orderId, $amount, $reason)
{
    // Load the order using OrderRepositoryInterface
    $order = $this->orderRepository->get($orderId);

    // Retrieve the plural_order_id from the sales_order table
    $pluralOrderId = $order->getData('plural_order_id');

    if (!$pluralOrderId) {
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Plural Order ID not found for order ID %1', $orderId)
        );
    }

    
   $env = $this->scopeConfig->getValue('payment/pinepgpaymentmethod/PayEnvironment', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  $url = ($env === 'LIVE')
        ? 'https://api.pluralpay.in/api/pay/v1/refunds/' . $pluralOrderId
        : 'https://pluraluat.v2.pinepg.in/api/pay/v1/refunds/' . $pluralOrderId;

    // Prepare the body for the refund request
    $body = json_encode([
        'merchant_order_reference' => uniqid().'magento_'.$orderId,
        'refund_amount' => ['value' => (int) $amount * 100, 'currency' => 'INR'],
        'refund_reason' => $reason,
    ]);

    $access_token = $this->getAccessToken();

    $merchant_id = $this->scopeConfig->getValue('payment/pinepgpaymentmethod/MerchantId', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

    $headers = [
		'Merchant-ID: ' . $merchant_id,
		'Authorization: Bearer ' . $access_token,
		'Content-Type: application/json',
	];

    // Send the request
    $response = $this->sendRequest($url, $body, $headers);


    // Process the response
    if (!empty($response['data']['status']) && $response['data']['status'] === 'PROCESSED') {
        // Get refund ID from the response
        $refundId = $response['data']['order_id'];

        // Update the sales_order table with plural_refund_id
        $order->setData('plural_refund_id', $refundId);
        $this->orderRepository->save($order);

        $payment = $order->getPayment();


         // Add a refund transaction
         $transaction = $this->transactionBuilder->setPayment($payment)
         ->setOrder($order)
         ->setTransactionId($order->getIncrementId() . '-refund')
         ->setAdditionalInformation([Transaction::RAW_DETAILS => ['Status' => 'Refunded']])
         ->setFailSafe(true)
         ->build(Transaction::TYPE_REFUND);

     // Mark the transaction as closed
     $payment->setIsTransactionClosed(1);

     // Update the order status to refunded
     $order->setStatus(Order::STATE_CLOSED);
     $order->setState(Order::STATE_CLOSED);

     // Save the transaction and order
     $transaction->save();
     $this->orderRepository->save($order);

        // Add a history comment to the order
        $order->addCommentToStatusHistory(__('Refund successful,<b>Pinelabs Refund Id: </b>: %1, Amount: %2', $refundId, $amount));
        $this->orderRepository->save($order);
        return ['status' => 'success'];
    } else {
        // Handle refund failure
        $errorMessage = $response['data']['message'] ?? 'Refund failed';
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Refund failed: %1', $errorMessage)
        );
    }
}



    public function getAccessToken()
	  {
		  

        $env = $this->scopeConfig->getValue('payment/pinepgpaymentmethod/PayEnvironment', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $merchantSecretKey = $this->scopeConfig->getValue('payment/pinepgpaymentmethod/MerchantSecretKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $merchantAccessCode = $this->scopeConfig->getValue('payment/pinepgpaymentmethod/MerchantAccessCode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);



        if ($env === 'LIVE') {
            $url = 'https://api.pluralpay.in/api/auth/v1/token';
        }else{
			$url = 'https://pluraluat.v2.pinepg.in/api/auth/v1/token';
		}

		  $body = json_encode([
			  'client_id' => $merchantAccessCode,
			  'client_secret' => $merchantSecretKey,
			  'grant_type' => 'client_credentials',
		  ]);
	  
		  $headers = [
			  'Content-Type: application/json',
		  ];
	  
		  // Initialize cURL
		  $curl = curl_init();
		  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		  curl_setopt_array($curl, [
			  CURLOPT_URL => $url,
			  CURLOPT_RETURNTRANSFER => true, // Return response as string
			  CURLOPT_HTTPHEADER => $headers, // Set the headers
			  CURLOPT_POST => true, // HTTP POST method
			  CURLOPT_POSTFIELDS => $body, // Set the POST data
		  ]);
	  
		  try {
			  // Execute the request and capture the response
			  $response = curl_exec($curl);
	  
			  if ($response === false) {
				  throw new \Exception('cURL Error: ' . curl_error($curl));
			  }
	  
			  $response = json_decode($response, true);
	  
			  if (isset($response['access_token'])) {
				  return $response['access_token'];
			  } else {
				  throw new \Exception('Failed to retrieve access token');
			  }
		  } catch (\Exception $e) {
			  throw new \Exception('Error during token retrieval: ' . $e->getMessage());
		  } finally {
			  curl_close($curl); // Close the cURL session
		  }
	  }


      public function sendRequest($url, $body, $headers)
{
    $curl = curl_init();
		  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		  
		  curl_setopt_array($curl, [
			  CURLOPT_URL => $url,
			  CURLOPT_RETURNTRANSFER => true, // Return response as string
			  CURLOPT_HTTPHEADER => $headers, // Set the headers
			  CURLOPT_POST => true, // HTTP POST method
			  CURLOPT_POSTFIELDS => $body, // Set the POST data
		  ]);

          try {
            // Execute the request and capture the response
            $response = curl_exec($curl);
    
            if ($response === false) {
                throw new \Exception('cURL Error: ' . curl_error($curl));
            }
    
            $response = json_decode($response, true);
    
            return $response;
        } catch (\Exception $e) {
            throw new \Exception('Error during Refund API request: ' . $e->getMessage());
        } finally {
            curl_close($curl); // Close the cURL session
        }
}



}
