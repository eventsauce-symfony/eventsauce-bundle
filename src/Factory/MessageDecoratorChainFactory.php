<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;

final class MessageDecoratorChainFactory
{
    /**
     * @param iterable<MessageDecorator> $decorators
     */
    public function __invoke(iterable $decorators): MessageDecorator
    {
        return new MessageDecoratorChain(...$decorators);
    }
}
