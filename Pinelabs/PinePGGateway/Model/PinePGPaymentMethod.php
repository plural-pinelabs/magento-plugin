<?php

namespace Pinelabs\PinePGGateway\Model;

use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Framework\Session\Config;
use Magento\Sales\Api\OrderRepositoryInterface;
use Pinelabs\PinePGGateway\Logger\Logger;

/**
 * Pay In Store payment method model
 */
class PinePGPaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
	const PAYMENT_PINE_PG_CODE = 'pinepgpaymentmethod';
    protected $_code = self::PAYMENT_PINE_PG_CODE;
    protected $_isOffline = true;
	private $checkoutSession;
	protected  $logger;
	protected  $pineLogger;
	private $orderRepository;

    /**
     * 
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Pinelabs\PinePGGateway\Helper\PinePG $helper,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Model\Cart $cart,
		\Magento\Directory\Model\Country $countryHelper,
		OrderRepositoryInterface $orderRepository,
		 Logger $pineLogger,
		\Magento\Payment\Model\Method\Logger $paymentLogger

		
    ) {
		$this->pineLogger  = $pineLogger;
        $this->helper = $helper;
        $this->httpClientFactory = $httpClientFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cart = $cart;
		$this->orderRepository = $orderRepository;
		$this->_countryHelper = $countryHelper;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
			$paymentLogger
        );

    }
	
	public function getRedirectUrl() {
        return $this->helper->getUrl($this->getConfigData('redirect_url'));
    }

    public function getReturnUrl() {
        return $this->helper->getUrl($this->getConfigData('return_url'));
    }

    public function getCancelUrl() {
        return $this->helper->getUrl($this->getConfigData('cancel_url'));
    }

    /**
     * Return url according to environment
     * @return string
     */
    public function getCgiUrl() {
        $env = $this->getConfigData('PayEnvironment');
        if ($env === 'LIVE') {
            return $this->getConfigData('production_url');
        }
        return $this->getConfigData('sandbox_url');
    }
	  public function Hex2String($hex){
            $string='';
            for ($i=0; $i < strlen($hex)-1; $i+=2){
                $string .= chr(hexdec($hex[$i].$hex[$i+1]));
            }
            return $string;
        }
		

    public function buildCheckoutRequest() {

		

		$TXN_TYPE_PURCHASE='1';
		$NAVIGATION_REDIRECT_MODE='2';
		$this->logger->info(__LINE__ . ' | '.__FUNCTION__);
		
        $order = $this->checkoutSession->getLastRealOrder();
        $billing_address = $order->getBillingAddress();
		$shipping_address = $order->getShippingAddress();
		        
		$params = array();
		//set billing address
		$params['ppc_CustomerFirstName'] 	= $billing_address->getData('firstname');
		$params['ppc_CustomerLastName'] 	= $billing_address->getData('lastname');
		$params['ppc_CustomerCountry'] 		= $billing_address->getData('country_id');
		$countryObj 						= $this->_countryHelper->loadByCode($params['ppc_CustomerCountry']);
		$params['ppc_CustomerCountry'] 		= $countryObj->getName();
		$params['ppc_CustomerState'] 		= $billing_address->getData('region');
		$params['ppc_CustomerCity'] 		= $billing_address->getData('city');
		
		$params['ppc_CustomerAddressPIN'] 	= $billing_address->getData('postcode');
		$params['ppc_CustomerEmail'] 		= $billing_address->getData('email');
		$params['ppc_CustomerMobile'] 		= $billing_address->getData('telephone');

		//set shipping address
		$params['ppc_ShippingFirstName'] 	 = $shipping_address->getData('firstname');
		$params['ppc_ShippingLastName'] 	 = $shipping_address->getData('lastname');
		
		$params['ppc_ShippingCity'] 		 = $shipping_address->getData('city');
		$params['ppc_ShippingState'] 		 = $shipping_address->getData('region');
		
		$params['ppc_ShippingCountry'] 	 	 = $shipping_address->getData('country_id');
		$countryObj 						 = $this->_countryHelper->loadByCode($params['ppc_ShippingCountry']);
		$params['ppc_ShippingCountry'] 		 = $countryObj->getName();
		
		$params['ppc_ShippingZipCode'] 	 	 = $shipping_address->getData('postcode');
		$params['ppc_ShippingPhoneNumer']  	 = $shipping_address->getData('telephone');

		$params['ppc_UdfField1'] 			 = 'Magento_2.3.4';
        $params["ppc_MerchantAccessCode"] 	 = $this->getConfigData("MerchantAccessCode");
        $secret_key 						 = $this->Hex2String($this->getConfigData("MerchantSecretKey"));
        $params["ppc_PayModeOnLandingPage"]  = $this->getConfigData("MerchantPaymentMode");
        $params["ppc_Carttype"] 			 = $this->getConfigData("cart");
        $params["ppc_LPC_SEQ"] 				 = '1';
		$params["ppc_Amount"] 				 = round($order->getBaseGrandTotal(), 2)*100;
        $params["ppc_NavigationMode"] 		 = $NAVIGATION_REDIRECT_MODE;
		$params["ppc_MerchantReturnURL"] 	 = $this->getReturnUrl();
        $params["ppc_TransactionType"] 		 = $TXN_TYPE_PURCHASE;
	    $params["ppc_UniqueMerchantTxnID"] 	 = uniqid().'_'.$this->checkoutSession->getLastRealOrderId(); 
	    $params["ppc_MerchantID"] 			 = $this->getConfigData("MerchantId");

	    $product_id ='';
	    $totalOrders = 0;
	    $IsProductQuantityInCartMoreThanOne=false;
		$quan=-1;
		 
		$params['ppc_MerchantProductInfo'] = '';
		$params['ppc_Product_Code']  ='';
		$product_info_data = [];
		$i = 0;
		foreach ($order->getAllVisibleItems()  as $product) {
			$this->logger->info(__LINE__ . ' | '.__FUNCTION__.' Get Product code of item and check whether there is more than one item present in cart or not');
			$totalOrders =$totalOrders+1;
			$product_id = $product->getSku();
			$quan=$product->getQty();
					   
			if($totalOrders == 1){
			    $params['ppc_MerchantProductInfo'] = $product->getName();
			}else{
				$params['ppc_MerchantProductInfo'] = $params['ppc_MerchantProductInfo'].'|'.$product->getName();
			}
						   
			if($product->getQtyOrdered()>1)
			{
				$IsProductQuantityInCartMoreThanOne=true; 
			} 
			
			$product_details = new \stdClass();

			$quantity = explode('.',$product->getQtyOrdered())[0];
			$product_details->product_code = $product->getSku();
			$product_details->product_amount = intval(floatval($product->getPrice()) * 100)*$quantity;
			$product_info_data[$i] = $product_details;
			$this->logger->info('quantity:'.$product->getDiscountAmount().'-discounts'.$quantity );	
			$i++;
        }
		
		$this->logger->info('price:'.$product->getPrice() );	
		$params = $this->checkCartType($product_info_data,$params,$order);

        if ($totalOrders == 1 && $IsProductQuantityInCartMoreThanOne==false )
        {
		    $this->logger->info(__LINE__ . ' | '.__FUNCTION__.' Item count is one and Product code is:'.$product_id );
            $params['ppc_Product_Code']  = $product_id;
		}
        else
        {
			$this->logger->info(__LINE__ . ' | '.__FUNCTION__.' Item count is more than one ' );
			$params['ppc_Product_Code']  ='';
        }
					
	  	ksort($params);
		$strString="";
	 
		 // convert dictionary key and value to a single string variable
		foreach ($params as $key => $val) {
			$strString.=$key."=".$val."&";
		}
	    $this->logger->info(__LINE__ . ' | '.__FUNCTION__.' Request paramter is: '.$strString );
		 // trim last character from string
		$strString = substr($strString, 0, -1);
		$code = strtoupper(hash_hmac('sha256', $strString, $secret_key));
        $this->logger->info(__LINE__ . ' | '.__FUNCTION__.' Hash of request is '.$code );
        $params['ppc_DIA_SECRET_TYPE'] = 'SHA256';
	    $params['ppc_DIA_SECRET'] = $code;	
		$this->logger->info(__LINE__ . ' | '.__FUNCTION__.' Parameters: '. json_encode($params));
        return $params;
    }
	 
	  //validate response

	public function callOrderApi($order)
{
    $this->pineLogger->info(__LINE__ . ' | ' . __FUNCTION__ . ' Complete Order Data: ' . json_encode($order->getData(), JSON_PRETTY_PRINT));

    $env = $this->getConfigData('PayEnvironment');
    $url = ($env === 'LIVE')
        ? 'https://api.pluralpay.in/api/checkout/v1/orders'
        : 'https://pluraluat.v2.pinepg.in/api/checkout/v1/orders';

    $callback_url = $this->getCallbackUrl();
    $telephone = $order->getBillingAddress()->getTelephone();
    $onlyNumbers = preg_replace('/\D/', '', $telephone) ?: '9999999999';

    $billingAddress = $order->getBillingAddress();
    $shippingAddress = $order->getShippingAddress();
    $billingAddressData = $billingAddress ?: $shippingAddress;

    $formatAddress = function ($address) {
        return [
            'address1' => substr($address->getStreetLine(1), 0, 99),
            'pincode'  => $address->getPostcode(),
            'city'     => $address->getCity(),
            'state'    => $address->getRegion(),
            'country'  => $address->getCountryId()
        ];
    };

    $billingData = $formatAddress($billingAddressData);
    $shippingData = $formatAddress($shippingAddress);

    $grandTotal = intval(round(floatval($order->getBaseGrandTotal()) * 100));
    $shippingAmount = intval(round(floatval($order->getBaseShippingInclTax()) * 100));
    $totalDiscountAmount = abs(intval(round(floatval($order->getBaseDiscountAmount()) * 100)));

    $products = [];
    $totalProductValue = 0;

    // Calculate total price incl. tax for all items (for proportional discount)
    $couponDiscount = abs(floatval($order->getBaseDiscountAmount()));
    $totalItemTaxIncl = 0.0;

    foreach ($order->getAllVisibleItems() as $item) {
        $qty = intval($item->getQtyOrdered());
        $priceInclTax = floatval($item->getBasePriceInclTax());
        $totalItemTaxIncl += ($priceInclTax * $qty);
    }

    foreach ($order->getAllVisibleItems() as $item) {
        $qty = intval($item->getQtyOrdered());
        if ($qty <= 0) continue;

        $priceInclTax = floatval($item->getBasePriceInclTax());
        $itemDiscount = abs(floatval($item->getBaseDiscountAmount()));
        $sku = $item->getSku() ?: 'ITEM_' . $item->getItemId() . '_' . rand(10000, 99999);

        $itemTotal = $priceInclTax * $qty;

        $cartDiscountShare = $totalItemTaxIncl > 0
            ? ($itemTotal / $totalItemTaxIncl) * $couponDiscount
            : 0;

        $totalDiscount = $itemDiscount + $cartDiscountShare;
        $finalItemPrice = ($itemTotal - $totalDiscount) / $qty;
        $finalItemPrice = max(0, $finalItemPrice);

        $finalItemPricePaise = intval(round($finalItemPrice * 100));

        if ($finalItemPricePaise <= 0) {
            $this->pineLogger->info("Skipping product with 0 price after discount: {$item->getName()} (SKU: $sku)");
            continue;
        }

        $this->pineLogger->info("Item: {$item->getName()}, SKU: $sku, PriceInclTax: $priceInclTax, Qty: $qty, ItemDiscount: $itemDiscount, CartDiscountShare: $cartDiscountShare, FinalPrice: $finalItemPricePaise");

        for ($i = 0; $i < $qty; $i++) {
            $products[] = [
                'product_code' => $sku,
                'product_amount' => [
                    'value' => $finalItemPricePaise,
                    'currency' => 'INR',
                ],
            ];
            $totalProductValue += $finalItemPricePaise;
        }
    }

    // Add shipping as a product
    if ($shippingAmount > 0) {
        $products[] = [
            'product_code' => 'shipping_charge',
            'product_amount' => [
                'value' => $shippingAmount,
                'currency' => 'INR',
            ],
        ];
        $this->pineLogger->info("Shipping added: ₹" . ($shippingAmount / 100));
        $totalProductValue += $shippingAmount;
    }

    // Rounding adjustment if needed
    $roundingAdjustment = $grandTotal - $totalProductValue;
    if ($roundingAdjustment > 0) {
        $products[] = [
            'product_code' => 'rounding_adjustment',
            'product_amount' => [
                'value' => $roundingAdjustment,
                'currency' => 'INR',
            ],
        ];
        $this->pineLogger->info("Adding rounding adjustment: ₹" . ($roundingAdjustment / 100));
        $totalProductValue += $roundingAdjustment;
    }

    // Final validation
    if (abs($grandTotal - $totalProductValue) > 1) {
        $this->pineLogger->error("Amount mismatch! GrandTotal: $grandTotal, Calculated: $totalProductValue");
        throw new \Exception("Amount calculation error - totals don't match");
    }

    $partPaymentEnabled = $this->getConfigData('enable_down_payment') === '1';
    $this->pineLogger->info("Down Payment Enabled? " . ($partPaymentEnabled ? 'YES' : 'NO'));

    // Final Payload
    $payload = [
        'merchant_order_reference' => $order->getIncrementId() . '_' . date("ymdHis"),
        'order_amount' => [
            'value' => $grandTotal,
            'currency' => 'INR',
        ],
        'callback_url' => $callback_url,
        'pre_auth' => false,
        'integration_mode' => "REDIRECT",
        "plugin_data" => [
            "plugin_type" => "Magento",
            "plugin_version" => "V3"
        ],
        'purchase_details' => [
            'customer' => [
                'email_id' => $billingAddressData->getEmail(),
                'first_name' => $billingAddressData->getFirstname(),
                'last_name' => $billingAddressData->getLastname(),
                'mobile_number' => $onlyNumbers,
                'billing_address' => $billingData,
                'shipping_address' => $shippingData,
            ],
            'products' => $products,
        ],
    ];

    if ($partPaymentEnabled) {
    $payload['part_payment'] = true;
}

    $payloadJson = json_encode($payload, JSON_PRETTY_PRINT);
    $this->pineLogger->info(__LINE__ . ' | ' . __FUNCTION__ . ' Request Payload: ' . $payloadJson);

    // Make API call
    $headers = [
        'Content-Type: application/json',
        'Merchant-ID: ' . $this->getConfigData("MerchantId"),
        'Authorization: Bearer ' . $this->getAccessToken(),
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payloadJson,
    ]);

    try {
        $response = curl_exec($curl);
        if ($response === false) {
            $this->pineLogger->info(__LINE__ . ' | ' . __FUNCTION__ . ' API call failed: ' . curl_error($curl));
            throw new \Exception('cURL Error: ' . curl_error($curl));
        }

        $response = json_decode($response, true);
        if (isset($response['redirect_url']) && $response['response_code'] === 200) {
            $order->setData('plural_order_id', $response['order_id']);
            $this->orderRepository->save($order);
            return $response['redirect_url'];
        } else {
            $this->pineLogger->info(__LINE__ . ' | ' . __FUNCTION__ . ' API failure: ' . json_encode($response));
            throw new \Exception($response['error_message'] ?? ($response['response_message'] ?? 'Unknown error'));
        }
    } catch (\Exception $e) {
        throw new \Exception('API Request Error: ' . $e->getMessage());
    } finally {
        curl_close($curl);
    }
}



	  



	  public function getCallbackUrl() {
		// Check if running locally
		if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
			// Local environment
			return 'http://localhost/magento/pinepg/standard/response';
		}
	
		// Production or staging environment
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
		$domain = $protocol . $_SERVER['HTTP_HOST'];
		
		return $domain . '/pinepg/standard/response';
	}


	public function callEnquiryApi($orderId) {

		

		$authorization = $this->getAccessToken();
		$env = $this->getConfigData('PayEnvironment');
	
		// Define the URL based on the environment
		$url = ($env === 'LIVE') 
			? "https://api.pluralpay.in/api/pay/v1/orders/$orderId"
			: "https://pluraluat.v2.pinepg.in/api/pay/v1/orders/$orderId";
	
		// Set the request headers
		$headers = [
			"Authorization: Bearer " . trim($authorization),
			"Content-Type: application/json"
		];
	
		// Initialize cURL
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true, // Return response as a string
			CURLOPT_HTTPHEADER => $headers, // Set the headers
			CURLOPT_SSL_VERIFYPEER => false // Ignore SSL certificate verification (if needed)
		]);
	
		try {
			// Execute the request and capture the response
			$response = curl_exec($curl);
	
			if ($response === false) {
				$this->pineLogger->info(__LINE__ . ' | '.__FUNCTION__.' Enquiry API Response for V3: '.  curl_error($curl));
				throw new \Exception('cURL Error: ' . curl_error($curl));
			}

			$this->pineLogger->info(__LINE__ . ' | '.__FUNCTION__.' Enquiry API Response for V3: '. $response);
	
			// Decode the JSON response
			$response = json_decode($response, true);
	
			if (isset($response)) {
				return $response; // Return the API response
			} else {
				throw new \Exception('Invalid response from API');
			}
		} catch (\Exception $e) {
			throw new \Exception('Error during API call: ' . $e->getMessage());
		} finally {
			curl_close($curl); // Close the cURL session
		}
	}
	  
	  public function getAccessToken()
	  {
		  

		  $env = $this->getConfigData('PayEnvironment');
        if ($env === 'LIVE') {
            $url = 'https://api.pluralpay.in/api/auth/v1/token';
        }else{
			$url = 'https://pluraluat.v2.pinepg.in/api/auth/v1/token';
		}

		  $body = json_encode([
			  'client_id' => $this->getConfigData("MerchantAccessCode"),
			  'client_secret' => $this->getConfigData("MerchantSecretKey"),
			  'grant_type' => 'client_credentials',
		  ]);
	  
		  $headers = [
			  'Content-Type: application/json',
		  ];
	  
		  // Initialize cURL
		  $curl = curl_init();
		  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
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

	


    public function postProcessing(\Magento\Sales\Model\Order $order, \Magento\Framework\DataObject $payment, $response) { 

		
 
		$payment->setTransactionId($response['order_id']);
        
		$dateTime=date('Y-m-d H:i:s');
		
				
        $payment->addTransaction("order");
        $payment->setIsTransactionClosed(0); 
        $payment->place();
        $order->setStatus('processing');
		// Add a comment to the order
		$order->addStatusHistoryComment('Payment successfull for order <b>Pinelabs Payment Id: </b>'.$response['order_id']. ', <b>Txn Status:</b> '. $response['status']);
        $order->save();
		$this->pineLogger->info(__LINE__ . ' | '.__FUNCTION__.' Save the order after successful response from Pine PG for order id:'.$response['order_id'].'and Pine PG Txn ID:'.$response['order_id'] );
    }

	private function checkCartType($product_info_data,$params,$order)
	{

		if($params['ppc_Carttype'] == 'MultiCart'){

			if($product_info_data){ 

				if($order->getDiscountAmount()){
					$discount_val = abs($order->getDiscountAmount());
					$productTotalAmt_beforeDiscount = $params['ppc_Amount'] + ($discount_val*100);

					$product_info_data = $this->calculation_on_items($product_info_data,$productTotalAmt_beforeDiscount,($discount_val*100));	
				}

				$ppc_MultiCartProductDetails = base64_encode(json_encode($product_info_data));
			
				$params['ppc_MultiCartProductDetails'] = base64_encode(json_encode($product_info_data));
			}
			else 
			{
				$params['ppc_MultiCartProductDetails'] = '';
				unset($params['ppc_MultiCartProductDetails']);
			}

		}
		unset($params['ppc_Carttype']);
		return $params;		
	}

	private function calculation_on_items($items,$total_amt,$discount){ 

		

		$this->pineLogger->info('PineItems - '.json_encode($items).' Ordertotal-amount-before-discount - '.$total_amt. ' Discount - '. $discount);

		foreach($items as $key => $value){
				$single_item_percentage = ($items[$key]->product_amount/$total_amt) * $discount;
				$get_amt = $items[$key]->product_amount - $single_item_percentage;
				$items[$key]->product_amount = $get_amt;
			}
		return $items;
	}
}