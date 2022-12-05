<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\Doubles;

use Andreo\EventSauceBundle\Attribute\EnableAcl;
use Andreo\EventSauceBundle\Enum\MessageFilterStrategy;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

#[EnableAcl(MessageFilterStrategy::MATCH_ANY, MessageFilterStrategy::MATCH_ALL)]
final class DummyAclMessageConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
    }
}
