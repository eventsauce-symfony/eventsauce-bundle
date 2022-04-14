<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\AclInboundTarget;
use Andreo\EventSauceBundle\Attribute\AclOutboundTarget;
use Andreo\EventSauceBundle\Attribute\AsMessageFilterAfter;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;

#[AsMessageFilterAfter(priority: 10)]
#[AclInboundTarget]
#[AclOutboundTarget]
final class DummyMessageFilterAfter implements MessageFilter
{
    public function allows(Message $message): bool
    {
    }
}
