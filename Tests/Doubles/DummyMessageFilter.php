<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\Doubles;

use Andreo\EventSauceBundle\Attribute\AsMessageFilter;
use Andreo\EventSauceBundle\Enum\MessageFilterTrigger;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

#[AsMessageFilter(
    trigger: MessageFilterTrigger::AFTER_TRANSLATE,
    priority: 10,
    owners: MessageConsumer::class
)]
final class DummyMessageFilter implements MessageFilter
{
    public function allows(Message $message): bool
    {
    }
}
