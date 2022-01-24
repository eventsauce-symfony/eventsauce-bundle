<?php

declare(strict_types=1);


namespace Tests\ConfigExtension;

use DateTimeImmutable;
use DateTimeZone;
use EventSauce\Clock\Clock;

class DummyCustomClock implements Clock
{

    public function now(): DateTimeImmutable
    {

    }

    public function timeZone(): DateTimeZone
    {

    }
}