<?php

declare(strict_types=1);

namespace Tests\Factory;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDispatcher;

final class DummyMessageDispatcher implements MessageDispatcher
{
    public function dispatch(Message ...$messages): void
    {
    }
}
