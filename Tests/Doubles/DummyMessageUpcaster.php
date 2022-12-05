<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\Doubles;

use Andreo\EventSauce\Upcasting\MessageUpcaster\MessageUpcaster;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use EventSauce\EventSourcing\Message;

#[AsUpcaster(aggregateClass: FooDummyAggregate::class, version: 2)]
class DummyMessageUpcaster implements MessageUpcaster
{
    public function upcast(Message $message): Message
    {
    }
}
