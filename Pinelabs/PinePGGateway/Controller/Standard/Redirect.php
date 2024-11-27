<?php

namespace Pinelabs\PinePGGateway\Controller\Standard;

class Redirect extends \Pinelabs\PinePGGateway\Controller\PinePGAbstract {

    public function execute() 
	{
			if (!$this->getRequest()->isAjax()) {
				$this->_cancelPayment();
				$this->_checkoutSession->restoreQuote();
				$this->getResponse()->setRedirect(
						$this->getCheckoutHelper()->getUrl('checkout')
				);
			}
		
			$order = $this->getOrder();
			$order->setState('processing')->setStatus('pending');
			$order->save();
			$quote = $this->getQuote();
			$email = $this->getRequest()->getParam('email');
			if ($this->getCustomerSession()->isLoggedIn()) {
				$this->getCheckoutSession()->loadCustomerQuote();
				$quote->updateCustomerData($this->getQuote()->getCustomer());
			} else {
				$quote->setCustomerEmail($email);
			}

			if ($this->getCustomerSession()->isLoggedIn()) {
				$quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER);
			} else {
				$quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);
			}
			
			$quote->setCustomerEmail($email);
			$quote->save();

        $params = [];
		
        /* commented v2 api of post method and change it with create api
		$params["fields"] = $this->getPaymentMethod()->buildCheckoutRequest();
        $params["url"] = $this->getPaymentMethod()->getCgiUrl();
		*/


		$params["url"] = $this->getPaymentMethod()->callOrderApi($order);

		//$params["url"] ="https://www.google.com/search?q=ssrmovies&sca_esv=40c7603c5a54795f&source=hp&ei=LiY-Z7-8LtCXnesPvMTNsAQ&iflsig=AL9hbdgAAAAAZz40PiHk48Ww9JO5wOkNVfevRkgIVqup&ved=0ahUKEwi_i_yQweuJAxXQS2cHHTxiE0YQ4dUDCBw&oq=ss&gs_lp=Egdnd3Mtd2l6IgJzcyoCCAEyCBAAGIAEGLEDMg4QABiABBixAxiDARiKBTILEAAYgAQYsQMYgwEyBRAAGIAEMggQABiABBixAzIIEAAYgAQYsQMyCBAAGIAEGLEDMggQABiABBixAzIIEAAYgAQYsQMyDhAAGIAEGLEDGIMBGIoFSOMLUG9Y9QJwAXgAkAEAmAGVAqABqQSqAQMyLTK4AQHIAQD4AQGYAgOgAsEEqAIKwgIKEAAYAxjqAhiPAcICChAuGAMY6gIYjwHCAgUQLhiABMICERAuGIAEGLEDGNEDGIMBGMcBwgILEC4YgAQY0QMYxwHCAggQLhiABBixA5gDD5IHBTEuMC4yoAfSEw&sclient=gws-wiz";
		

/*
$parsedUrl = parse_url($params["url"]);
$baseUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}{$parsedUrl['path']}";

$query = [];
if (isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $query); // Parse query string into an associative array
}
$token = $query['token'] ?? null;
$params["url"] = $baseUrl; 
$params["fields"] = [
    'token' => $token
];*/

        return $this->resultJsonFactory->create()->setData($params);
    }

}
