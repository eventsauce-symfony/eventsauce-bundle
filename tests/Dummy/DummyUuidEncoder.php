<?php
declare(strict_types=1);

namespace Tests\Dummy;

use EventSauce\UuidEncoding\UuidEncoder;
use Ramsey\Uuid\UuidInterface;

class DummyUuidEncoder implements UuidEncoder
{
    public function encodeUuid(UuidInterface $uuid): string
    {
    }

    public function encodeString(string $uuid): string
    {
    }
}