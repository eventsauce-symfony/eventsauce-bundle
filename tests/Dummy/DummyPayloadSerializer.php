<?php

declare(strict_types=1);

namespace Tests\Dummy;

use EventSauce\EventSourcing\Serialization\PayloadSerializer;

class DummyPayloadSerializer implements PayloadSerializer
{
    public function serializePayload(object $event): array
    {
    }

    public function unserializePayload(string $className, array $payload): object
    {
    }
}
