<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use EventSauce\BackOff\BackOffStrategy;
use Throwable;

class DummyBackOfStrategy implements BackOffStrategy
{
    public function backOff(int $tries, Throwable $throwable): void
    {
    }
}
