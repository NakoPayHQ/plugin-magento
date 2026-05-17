<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Controller\Payment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use NakoPay\Magento2\Logger\Logger;
use NakoPay\Magento2\Model\ApiClient;

/**
 * Webhook endpoint for NakoPay invoice status changes.
 *
 * URL: /nakopay/payment/webhook
 *
 * Verifies HMAC-SHA256 signature before processing.
 * Handles: invoice.paid, invoice.expired, invoice.canceled
 */
class Webhook implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ApiClient $apiClient,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Logger $logger,
    ) {}

    /**
     * Disable CSRF validation for webhook endpoint.
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $rawBody = (string) $this->request->getContent();
        $signatureHeader = $this->request->getHeader('X-NakoPay-Signature');

        // 1. Verify webhook signature (HMAC-SHA256)
        try {
            $event = $this->apiClient->verifyWebhookSignature($rawBody, $signatureHeader);
        } catch (\Throwable $e) {
            $this->logger->warning('NakoPay webhook signature rejected: ' . $e->getMessage());
            return $result->setData(['error' => 'Invalid signature'])->setHttpResponseCode(400);
        }

        $eventType = $event['type'] ?? '';
        $invoiceData = $event['data']['object'] ?? [];
        $invoiceId = $invoiceData['id'] ?? '';
        $metadata = $invoiceData['metadata'] ?? [];
        $magentoOrderId = $metadata['magento_order_id'] ?? '';

        $this->logger->info("NakoPay webhook received: {$eventType}", [
            'invoice_id' => $invoiceId,
            'order_id' => $magentoOrderId,
        ]);

        if ($magentoOrderId === '') {
            $this->logger->warning('NakoPay webhook missing magento_order_id in metadata');
            return $result->setData(['received' => true]);
        }

        // 2. Find the Magento order
        $order = $this->findOrder($magentoOrderId);
        if ($order === null) {
            $this->logger->warning("NakoPay webhook: order {$magentoOrderId} not found");
            return $result->setData(['received' => true]);
        }

        // 3. Handle event types
        switch ($eventType) {
            case 'invoice.paid':
                $this->handlePaid($order, $invoiceData);
                break;
            case 'invoice.expired':
                $this->handleExpired($order);
                break;
            case 'invoice.canceled':
                $this->handleCanceled($order);
                break;
            default:
                $this->logger->info("NakoPay webhook: unhandled event type {$eventType}");
        }

        return $result->setData(['received' => true]);
    }

    private function handlePaid(Order $order, array $invoiceData): void
    {
        if ($order->getState() === Order::STATE_PROCESSING || $order->getState() === Order::STATE_COMPLETE) {
            $this->logger->info('NakoPay: order already processed, skipping');
            return;
        }

        $targetStatus = $this->scopeConfig->getValue(
            'payment/nakopay/order_status',
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId(),
        ) ?: Order::STATE_PROCESSING;

        $amountCrypto = $invoiceData['amount_crypto'] ?? '';
        $coin = $invoiceData['coin'] ?? '';
        $txId = $invoiceData['tx_id'] ?? '';

        $comment = sprintf(
            'NakoPay payment confirmed. %s %s (tx: %s)',
            $amountCrypto,
            $coin,
            $txId ?: 'n/a',
        );

        $order->setState($targetStatus);
        $order->setStatus($targetStatus);
        $order->addCommentToStatusHistory($comment, false);
        $this->orderRepo->save($order);

        $this->logger->info("NakoPay: order {$order->getIncrementId()} marked as {$targetStatus}");
    }

    private function handleExpired(Order $order): void
    {
        if ($order->getState() === Order::STATE_CANCELED) {
            return;
        }
        $order->cancel();
        $order->addCommentToStatusHistory('NakoPay invoice expired - order canceled automatically.', false);
        $this->orderRepo->save($order);
        $this->logger->info("NakoPay: order {$order->getIncrementId()} canceled (invoice expired)");
    }

    private function handleCanceled(Order $order): void
    {
        if ($order->getState() === Order::STATE_CANCELED) {
            return;
        }
        $order->cancel();
        $order->addCommentToStatusHistory('NakoPay invoice canceled.', false);
        $this->orderRepo->save($order);
        $this->logger->info("NakoPay: order {$order->getIncrementId()} canceled");
    }

    private function findOrder(string $incrementId): ?Order
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->setPageSize(1)
            ->create();

        $orders = $this->orderRepo->getList($criteria)->getItems();
        $order = reset($orders);
        return $order instanceof Order ? $order : null;
    }
}
