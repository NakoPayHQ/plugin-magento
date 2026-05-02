<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Customer returns here after paying on NakoPay's hosted checkout.
 * We redirect to the standard Magento success page.
 *
 * URL: /nakopay/payment/callback
 */
class Callback implements HttpGetActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
    ) {}

    public function execute()
    {
        return $this->redirectFactory->create()->setPath('checkout/onepage/success');
    }
}
