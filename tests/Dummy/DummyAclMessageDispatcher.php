<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\WithOutboundAcl;
use Andreo\EventSauceBundle\Enum\FilterStrategy;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDispatcher;

#[WithOutboundAcl(FilterStrategy::MATCH_ANY, FilterStrategy::MATCH_ANY)]
final class DummyAclMessageDispatcher implements MessageDispatcher
{
    public function dispatch(Message ...$messages): void
    {
    }
}
