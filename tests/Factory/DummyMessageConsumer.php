<?php

declare(strict_types=1);

namespace Tests\Factory;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

final class DummyMessageConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
    }
}
