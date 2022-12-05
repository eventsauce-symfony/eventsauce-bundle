<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\Doubles;

use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;

#[AsMessageDecorator(priority: 10)]
class DummyMessageDecorator implements MessageDecorator
{
    public function decorate(Message $message): Message
    {
    }
}
