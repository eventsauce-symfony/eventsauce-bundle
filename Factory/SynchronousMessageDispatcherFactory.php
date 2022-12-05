<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;

final readonly class SynchronousMessageDispatcherFactory
{
    /**
     * @param iterable<MessageConsumer> $consumers
     */
    public static function create(iterable $consumers): MessageDispatcher
    {
        return new SynchronousMessageDispatcher(...$consumers);
    }
}
