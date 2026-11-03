<?php

namespace Pinelabs\PinePGGateway\Controller\Adminhtml\Refund;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Psr\Log\LoggerInterface;
use Pinelabs\PinePGGateway\Helper\PinePG;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Pinelabs_PinePGGateway::refund';

    protected $orderRepository;
    protected $redirectFactory;
    protected $logger;
    protected $messageManager;
    protected $pinePGHelper;

    /**
     * Constructor
     *
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        FormKeyValidator $formKeyValidator,
        LoggerInterface $logger,
        PinePG $pinePGHelper
    ) {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->_formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
        $this->pinePGHelper = $pinePGHelper;

        $this->logger = \Pinelabs\PinePGGateway\Helper\Logger::getLogger();
    }

    /**
     * Execute method
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        

        $resultRedirect = $this->redirectFactory->create();

        try {
            

            $orderId = $this->getRequest()->getParam('order_id');
            $amount = $this->getRequest()->getParam('amount');
            $reason = $this->getRequest()->getParam('reason') ?: 'Magento Admin Refund';

            $this->logger->info('Refund process started with order id '.$orderId.' and amount is '.$amount);

            // Basic parameter validation
            if (!$orderId || !is_numeric($amount) || (float) $amount <= 0) {
                throw new \Exception(__('Missing required parameters: order_id or amount.'));
            }

            $amount = round((float) $amount, 2);

            // Load the order
            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getId()) {
                throw new \Exception(__('Order not found with ID: %1', $orderId));
            }

            $remainingRefundableAmount = round(
                (float) $order->getGrandTotal() - (float) $order->getTotalRefunded(),
                2
            );

            if ($amount > $remainingRefundableAmount) {
                throw new \Exception(__(
                    'Refund amount %1 exceeds the remaining refundable amount %2.',
                    $amount,
                    $remainingRefundableAmount
                ));
            }

            $isFullRefund = abs($amount - $remainingRefundableAmount) < 0.01;

            $this->logger->info('Order loaded for refund.', [
                'order_id' => $order->getIncrementId(),
                'requested_amount' => $amount,
                'remaining_refundable_amount' => $remainingRefundableAmount,
                'is_full_refund' => $isFullRefund
            ]);

            // Process the refund
            $refundResponse = $this->pinePGHelper->processRefund(
                $order->getId(),
                $amount,
                $reason,
                true,
                $isFullRefund
            );

            $this->logger->info('Refund Response for order id '.$orderId.' Response: '. json_encode($refundResponse));

            // Handle the refund response
            if ($refundResponse['status'] === 'success') {
                $this->messageManager->addSuccessMessage(__('Refund processed successfully.'));
                $this->logger->info('Refund successful.');
            } else {
                throw new \Exception($refundResponse['message']);
            }

            return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
        } catch (\Exception $e) {
            $this->logger->err('Refund process error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Error: %1', $e->getMessage()));
            return $resultRedirect->setPath('sales/order/index');
        }
    }

    /**
     * Custom function to process the refund
     *
     * @param $order
     * @param $amount
     * @param $reason
     * @return array
     */
    

    /**
     * Check ACL resource permission
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
