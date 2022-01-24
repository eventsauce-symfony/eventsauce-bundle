<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\MessageSerializer;

final class DummyCustomMessageSerializer implements MessageSerializer
{
    public function serializeMessage(Message $message): array
    {
    }

    public function unserializePayload(array $payload): Message
    {
    }
}
