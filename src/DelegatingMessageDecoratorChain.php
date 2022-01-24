<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;

final class DelegatingMessageDecoratorChain implements MessageDecorator
{
    private MessageDecoratorChain $decoratorChain;

    /**
     * @param iterable<MessageDecorator> $processors
     */
    public function __construct(iterable $processors)
    {
        $this->decoratorChain = new MessageDecoratorChain(...$processors);
    }

    public function decorate(Message $message): Message
    {
        return $this->decoratorChain->decorate($message);
    }
}
