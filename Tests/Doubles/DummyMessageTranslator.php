<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\Doubles;

use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDispatcher;

#[AsMessageTranslator(owners: MessageDispatcher::class)]
final class DummyMessageTranslator implements MessageTranslator
{
    public function translateMessage(Message $message): Message
    {
    }
}
