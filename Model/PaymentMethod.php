<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Model;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * NakoPay payment method - redirect to hosted checkout.
 */
class PaymentMethod extends AbstractMethod
{
    public const CODE = 'nakopay';

    protected $_code = self::CODE;
    protected $_isOffline = false;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = false;
    protected $_canUseCheckout = true;
    protected $_canUseInternal = false;

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null): bool
    {
        $apiKey = $this->getConfigData('api_key');
        if (empty($apiKey)) {
            return false;
        }
        return parent::isAvailable($quote);
    }
}
