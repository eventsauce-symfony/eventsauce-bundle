<?php

declare(strict_types=1);

namespace Tests\Dummy;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\MessageSerializer;

class DummyMessageSerializer implements MessageSerializer
{
    public function serializeMessage(Message $message): array
    {
    }

    public function unserializePayload(array $payload): Message
    {
    }
}
