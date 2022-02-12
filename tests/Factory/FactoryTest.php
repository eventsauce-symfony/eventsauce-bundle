<?php

declare(strict_types=1);

namespace Tests\Factory;

use Andreo\EventSauceBundle\Factory\MessageDecoratorChainFactory;
use Andreo\EventSauceBundle\Factory\MessageDispatcherChainFactory;
use Andreo\EventSauceBundle\Factory\SynchronousMessageDispatcherFactory;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\MessageDispatcherChain;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use PHPUnit\Framework\TestCase;
use Tests\Factory\Dummy\DummyMessageConsumer;
use Tests\Factory\Dummy\DummyMessageDecorator;
use Tests\Factory\Dummy\DummyMessageDispatcher;

final class FactoryTest extends TestCase
{
    /**
     * @test
     */
    public function should_create_message_decorator_chain(): void
    {
        $factory = new MessageDecoratorChainFactory();
        $chain = $factory([new DummyMessageDecorator(), new DummyMessageDecorator()]);
        $this->assertInstanceOf(MessageDecoratorChain::class, $chain);
    }

    /**
     * @test
     */
    public function should_create_message_dispatcher_chain(): void
    {
        $factory = new MessageDispatcherChainFactory();
        $chain = $factory([new DummyMessageDispatcher(), new DummyMessageDispatcher()]);
        $this->assertInstanceOf(MessageDispatcherChain::class, $chain);
    }

    /**
     * @test
     */
    public function should_create_synchronous_message_dispatcher_chain(): void
    {
        $factory = new SynchronousMessageDispatcherFactory();
        $dispatcher = $factory([new DummyMessageConsumer(), new DummyMessageConsumer()]);
        $this->assertInstanceOf(SynchronousMessageDispatcher::class, $dispatcher);
    }
}
