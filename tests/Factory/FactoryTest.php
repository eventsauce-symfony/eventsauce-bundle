<?php

declare(strict_types=1);

namespace Tests\Factory;

use Andreo\EventSauceBundle\MessageDecoratorChainFactory;
use Andreo\EventSauceBundle\MessageDispatcherChainFactory;
use Andreo\EventSauceBundle\SynchronousMessageDispatcherFactory;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\MessageDispatcherChain;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use PHPUnit\Framework\TestCase;

final class FactoryTest extends TestCase
{
    /**
     * @test
     */
    public function message_decorator_chain_has_been_created(): void
    {
        $factory = new MessageDecoratorChainFactory();
        $chain = $factory([new DummyMessageDecorator(), new DummyMessageDecorator()]);
        $this->assertInstanceOf(MessageDecoratorChain::class, $chain);
    }

    /**
     * @test
     */
    public function message_dispatcher_chain_has_been_created(): void
    {
        $factory = new MessageDispatcherChainFactory();
        $chain = $factory([new DummyMessageDispatcher(), new DummyMessageDispatcher()]);
        $this->assertInstanceOf(MessageDispatcherChain::class, $chain);
    }

    /**
     * @test
     */
    public function synchronous_message_dispatcher_has_been_created(): void
    {
        $factory = new SynchronousMessageDispatcherFactory();
        $dispatcher = $factory([new DummyMessageConsumer(), new DummyMessageConsumer()]);
        $this->assertInstanceOf(SynchronousMessageDispatcher::class, $dispatcher);
    }
}
