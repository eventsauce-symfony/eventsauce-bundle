<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;

final class SynchronousMessageDispatcherFactory
{
    /**
     * @param iterable<MessageConsumer> $consumers
     */
    public function __invoke(iterable $consumers): SynchronousMessageDispatcher
    {
        return new SynchronousMessageDispatcher(...$consumers);
    }
}
