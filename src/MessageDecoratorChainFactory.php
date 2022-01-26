<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;

final class MessageDecoratorChainFactory
{
    /**
     * @param iterable<MessageDecorator> $decorators
     */
    public function __invoke(iterable $decorators): MessageDecoratorChain
    {
        return new MessageDecoratorChain(...$decorators);
    }
}
