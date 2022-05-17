<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\AsMessageFilter;
use Andreo\EventSauceBundle\Attribute\ForInboundAcl;
use Andreo\EventSauceBundle\Attribute\ForOutboundAcl;
use Andreo\EventSauceBundle\Enum\FilterPosition;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;

#[ForInboundAcl]
#[ForOutboundAcl]
#[AsMessageFilter(position: FilterPosition::BEFORE)]
final class DummyMessageFilterBefore implements MessageFilter
{
    public function allows(Message $message): bool
    {
    }
}
