<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\Doubles;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

class DummyMessageConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
    }
}
