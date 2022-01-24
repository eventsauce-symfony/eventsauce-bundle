<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageDispatcherChain;

final class DelegatingMessageDispatcherChain implements MessageDispatcher
{
    private MessageDispatcherChain $dispatcherChain;

    /**
     * @param iterable<MessageDispatcher> $dispatchers
     */
    public function __construct(iterable $dispatchers)
    {
        $this->dispatcherChain = new MessageDispatcherChain(...$dispatchers);
    }

    public function dispatch(Message ...$messages): void
    {
        $this->dispatcherChain->dispatch(...$messages);
    }
}
