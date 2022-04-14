<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\Acl;
use Andreo\EventSauceBundle\Attribute\AclMessageFilterChain;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDispatcher;

#[Acl]
#[AclMessageFilterChain(beforeTranslate: 'match_any', afterTranslate: 'match_any')]
final class DummyAclMessageDispatcher implements MessageDispatcher
{
    public function dispatch(Message ...$messages): void
    {
    }
}
