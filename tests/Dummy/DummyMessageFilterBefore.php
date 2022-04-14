<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\AclInboundTarget;
use Andreo\EventSauceBundle\Attribute\AclOutboundTarget;
use Andreo\EventSauceBundle\Attribute\AsMessageFilterBefore;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;

#[AsMessageFilterBefore]
#[AclInboundTarget]
#[AclOutboundTarget]
final class DummyMessageFilterBefore implements MessageFilter
{
    public function allows(Message $message): bool
    {
    }
}
