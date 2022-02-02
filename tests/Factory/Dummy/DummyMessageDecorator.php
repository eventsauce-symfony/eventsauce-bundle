<?php

declare(strict_types=1);

namespace Tests\Factory\Dummy;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;

final class DummyMessageDecorator implements MessageDecorator
{
    public function decorate(Message $message): Message
    {
    }
}
