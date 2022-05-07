<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\AsMessageFilterAfter;
use Andreo\EventSauceBundle\Attribute\ForInboundAcl;
use Andreo\EventSauceBundle\Attribute\ForOutboundAcl;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;

#[AsMessageFilterAfter(priority: 10)]
#[ForInboundAcl]
#[ForOutboundAcl]
final class DummyMessageFilterAfter implements MessageFilter
{
    public function allows(Message $message): bool
    {
    }
}
