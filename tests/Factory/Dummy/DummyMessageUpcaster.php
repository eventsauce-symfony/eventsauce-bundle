<?php

declare(strict_types=1);

namespace Tests\Factory\Dummy;

use Andreo\EventSauce\Upcasting\MessageUpcaster;
use EventSauce\EventSourcing\Message;

final class DummyMessageUpcaster implements MessageUpcaster
{
    public function upcast(Message $message): Message
    {
    }
}
