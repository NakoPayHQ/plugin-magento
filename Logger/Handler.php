<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Handler extends StreamHandler
{
    public function __construct(
        \Magento\Framework\Filesystem\Driver\File $filesystem,
    ) {
        parent::__construct(BP . '/var/log/nakopay.log', Logger::DEBUG);
    }
}
