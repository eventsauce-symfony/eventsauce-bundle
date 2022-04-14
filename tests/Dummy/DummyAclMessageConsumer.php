<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\Acl;
use Andreo\EventSauceBundle\Attribute\AclMessageFilterChain;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

#[Acl]
#[AclMessageFilterChain(beforeTranslate: 'match_any', afterTranslate: 'match_all')]
final class DummyAclMessageConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
    }
}
