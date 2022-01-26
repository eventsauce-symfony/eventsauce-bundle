<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageDispatcherChain;

final class MessageDispatcherChainFactory
{
    /**
     * @param iterable<MessageDispatcher> $dispatchers
     */
    public function __invoke(iterable $dispatchers): MessageDispatcherChain
    {
        return new MessageDispatcherChain(...$dispatchers);
    }
}
