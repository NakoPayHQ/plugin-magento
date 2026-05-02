<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use NakoPay\Magento2\Logger\Logger;
use NakoPay\Magento2\Model\ApiClient;

/**
 * Redirect the customer to NakoPay's hosted checkout page.
 * Called after Magento places the order with payment method "nakopay".
 *
 * URL: /nakopay/payment/redirect
 */
class Redirect implements HttpGetActionInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly ApiClient $apiClient,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly Logger $logger,
    ) {}

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getId()) {
            $this->messageManager->addErrorMessage(__('No order found. Please try again.'));
            return $redirect->setPath('checkout/cart');
        }

        try {
            $invoice = $this->apiClient->createInvoice([
                'amount' => number_format((float) $order->getGrandTotal(), 2, '.', ''),
                'currency' => $order->getOrderCurrencyCode(),
                'coin' => $this->apiClient->getBaseUrl() ? 'BTC' : 'BTC', // uses admin config
                'description' => sprintf('Order #%s', $order->getIncrementId()),
                'customer_email' => $order->getCustomerEmail(),
                'metadata' => [
                    'magento_order_id' => (string) $order->getIncrementId(),
                    'magento_store_id' => (string) $order->getStoreId(),
                ],
                'redirect_url' => $this->getReturnUrl($order),
            ]);

            // Store the NakoPay invoice ID on the order for webhook matching
            $order->setExtOrderId($invoice['id'] ?? '');
            $order->addCommentToStatusHistory(
                sprintf('NakoPay invoice created: %s', $invoice['id'] ?? 'unknown'),
                false,
            );
            $this->orderRepo->save($order);

            $checkoutUrl = $invoice['checkout_url'] ?? null;
            if (!$checkoutUrl) {
                throw new \RuntimeException('No checkout_url returned from NakoPay API');
            }

            return $redirect->setUrl($checkoutUrl);
        } catch (\Throwable $e) {
            $this->logger->error('NakoPay redirect failed: ' . $e->getMessage(), [
                'order_id' => $order->getIncrementId(),
            ]);
            $this->messageManager->addErrorMessage(
                __('Unable to create crypto payment. Please try again or choose a different payment method.')
            );
            return $redirect->setPath('checkout/cart');
        }
    }

    private function getReturnUrl($order): string
    {
        // Return URL after payment - customer lands back on success page
        return rtrim((string) $order->getStore()->getBaseUrl(), '/') . '/nakopay/payment/callback';
    }
}
