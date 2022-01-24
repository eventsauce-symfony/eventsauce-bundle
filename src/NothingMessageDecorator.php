<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;

final class NothingMessageDecorator implements MessageDecorator
{
    public function decorate(Message $message): Message
    {
        return $message;
    }
}
