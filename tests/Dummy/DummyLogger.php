<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Psr\Log\AbstractLogger;
use Stringable;

class DummyLogger extends AbstractLogger
{
    public function log($level, Stringable|string $message, array $context = []): void
    {
    }
}
