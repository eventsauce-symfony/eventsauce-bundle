<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\WithInboundAcl;
use Andreo\EventSauceBundle\Enum\FilterStrategy;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

#[WithInboundAcl(FilterStrategy::MATCH_ANY, FilterStrategy::MATCH_ALL)]
final class DummyAclMessageConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
    }
}
