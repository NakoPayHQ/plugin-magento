<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Coin implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'BTC', 'label' => __('Bitcoin (BTC)')],
            ['value' => 'LN', 'label' => __('Lightning (LN)')],
            ['value' => 'LTC', 'label' => __('Litecoin (LTC)')],
            ['value' => 'XMR', 'label' => __('Monero (XMR)')],
        ];
    }
}
