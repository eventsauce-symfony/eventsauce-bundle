<?php
declare(strict_types=1);

namespace Tests\Dummy;

use EventSauce\Clock\Clock;
use DateTimeImmutable, DateTimeZone;

class DummyClock implements Clock
{
    public function now(): DateTimeImmutable
    {
    }

    public function timeZone(): DateTimeZone
    {
    }
}