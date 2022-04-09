<?php

declare(strict_types=1);

namespace Tests\Dummy;

use DateTimeImmutable;
use DateTimeZone;
use EventSauce\Clock\Clock;

class DummyClock implements Clock
{
    public function now(): DateTimeImmutable
    {
    }

    public function timeZone(): DateTimeZone
    {
    }
}
