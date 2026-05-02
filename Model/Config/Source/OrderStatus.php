<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Model\Config\Source;

use Magento\Sales\Model\Order;
use Magento\Framework\Data\OptionSourceInterface;

class OrderStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Order::STATE_PROCESSING, 'label' => __('Processing')],
            ['value' => Order::STATE_COMPLETE, 'label' => __('Complete')],
        ];
    }
}
