<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;

final class DelegatingSynchronousMessageDispatcher implements MessageDispatcher
{
    private SynchronousMessageDispatcher $originDispatcher;

    /**
     * @param iterable<MessageConsumer> $consumers
     */
    public function __construct(iterable $consumers)
    {
        $this->originDispatcher = new SynchronousMessageDispatcher(...$consumers);
    }

    public function dispatch(Message ...$messages): void
    {
        $this->originDispatcher->dispatch(...$messages);
    }
}
