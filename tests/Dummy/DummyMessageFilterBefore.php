<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\AsMessageFilterBefore;
use Andreo\EventSauceBundle\Attribute\ForInboundAcl;
use Andreo\EventSauceBundle\Attribute\ForOutboundAcl;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;

#[AsMessageFilterBefore]
#[ForInboundAcl]
#[ForOutboundAcl]
final class DummyMessageFilterBefore implements MessageFilter
{
    public function allows(Message $message): bool
    {
    }
}
