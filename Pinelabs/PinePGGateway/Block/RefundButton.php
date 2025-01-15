<?php

namespace Pinelabs\PinePGGateway\Block;

use Magento\Backend\Block\Template;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;

class RefundButton extends Template
{
    protected $orderRepository;
    protected $coreRegistry;

    public function __construct(
        Template\Context $context,
        OrderRepositoryInterface $orderRepository,
        Registry $coreRegistry,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->coreRegistry = $coreRegistry;
        parent::__construct($context, $data);
    }

    /**
     * Generate the URL for the refund action.
     *
     * @return string
     */
    public function getRefundUrl()
    {
        try {
            return $this->getUrl('pinepg/refund/index'); // Adjust the route if needed
        } catch (LocalizedException $e) {
            return ''; // Return an empty string if the URL generation fails
        }
    }

    /**
     * Retrieve the Order ID for refund.
     *
     * @return int|null
     */
    public function getOrderId()
    {
        $order = $this->getCurrentOrder();
        return $order ? $order->getEntityId() : null;  // Return the order increment ID
    }

    /**
     * Retrieve the refund amount.
     *
     * @return float|null
     */
    public function getAmount()
    {
        $order = $this->getCurrentOrder();
        return $order ? $order->getGrandTotal() : null; // Return the grand total amount
    }

    /**
     * Retrieve the current order from the registry.
     *
     * @return \Magento\Sales\Model\Order|null
     */
    protected function getCurrentOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }
}
