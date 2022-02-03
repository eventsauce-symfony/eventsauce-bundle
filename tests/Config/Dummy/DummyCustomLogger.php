<?php

declare(strict_types=1);

namespace Tests\Config\Dummy;

use Psr\Log\AbstractLogger;
use Stringable;

class DummyCustomLogger extends AbstractLogger
{
    public function log($level, Stringable|string $message, array $context = []): void
    {
    }
}
