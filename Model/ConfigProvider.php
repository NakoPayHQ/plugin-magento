<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {}

    public function getConfig(): array
    {
        return [
            'payment' => [
                'nakopay' => [
                    'title' => $this->scopeConfig->getValue('payment/nakopay/title', ScopeInterface::SCOPE_STORE) ?: 'Pay with Bitcoin & Crypto',
                    'description' => $this->scopeConfig->getValue('payment/nakopay/description', ScopeInterface::SCOPE_STORE) ?: '',
                ],
            ],
        ];
    }
}
