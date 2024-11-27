<?php

namespace Pinelabs\PinePGGateway\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Redirectoutside extends Action
{
    /**
     * Constructor
     *
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Execute Method
     */
    public function execute()
    {
        // Here you can handle POST or GET requests
        if ($this->getRequest()->isPost()) {
            // Handle POST request
            $postData = $this->getRequest()->getPostValue();
            // Process the POST data
            echo 'POST data received: ';
            print_r($postData);
            exit;
        } else {
            echo 'This is not a POST request!';
            exit;
        }
    }
}
