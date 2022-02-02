<?php

declare(strict_types=1);

namespace Tests\Config\Dummy;

use EventSauce\BackOff\BackOffStrategy;
use Throwable;

class DummyCustomBackOfStrategy implements BackOffStrategy
{
    public function backOff(int $tries, Throwable $throwable): void
    {
    }
}
