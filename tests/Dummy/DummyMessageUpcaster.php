<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauce\Upcasting\MessageUpcaster;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use EventSauce\EventSourcing\Message;

#[AsUpcaster(aggregate: 'dummy', version: 2)]
class DummyMessageUpcaster implements MessageUpcaster
{
    public function upcast(Message $message): Message
    {
    }
}
