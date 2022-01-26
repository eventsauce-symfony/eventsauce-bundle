<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;

final class MessageDecoratorChainFactory
{
    /**
     * @param iterable<MessageDecorator> $processors
     */
    public function __invoke(iterable $processors): MessageDecoratorChain
    {
        return new MessageDecoratorChain(...$processors);
    }
}
