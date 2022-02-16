<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageDispatcherChain;

final class MessageDispatcherChainFactory
{
    /**
     * @param iterable<MessageDispatcher> $dispatchers
     */
    public static function create(iterable $dispatchers): MessageDispatcher
    {
        return new MessageDispatcherChain(...$dispatchers);
    }
}
