<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\InboundAcl;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

#[InboundAcl('match_any', 'match_all')]
final class DummyAclMessageConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
    }
}
