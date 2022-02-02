<?php

declare(strict_types=1);

namespace Tests\ConfigExtension\Dummy;

use EventSauce\EventSourcing\Serialization\PayloadSerializer;

class DummyCustomPayloadSerializer implements PayloadSerializer
{
    public function serializePayload(object $event): array
    {
    }

    public function unserializePayload(string $className, array $payload): object
    {
    }
}
