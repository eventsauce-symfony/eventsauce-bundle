<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;

final readonly class MessageDecoratorChainFactory
{
    /**
     * @param iterable<MessageDecorator> $decorators
     */
    public static function create(iterable $decorators): MessageDecorator
    {
        return new MessageDecoratorChain(...$decorators);
    }
}
