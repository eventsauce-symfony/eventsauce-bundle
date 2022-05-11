<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\WithInboundAcl;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

#[WithInboundAcl('match_any', 'match_all')]
final class DummyAclMessageConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
    }
}
