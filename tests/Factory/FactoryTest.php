<?php

declare(strict_types=1);

namespace Tests\Factory;

use Andreo\EventSauce\Upcasting\MessageUpcaster;
use Andreo\EventSauceBundle\Factory\MessageDecoratorChainFactory;
use Andreo\EventSauceBundle\Factory\MessageDispatcherChainFactory;
use Andreo\EventSauceBundle\Factory\MessageUpcasterChainFactory;
use Andreo\EventSauceBundle\Factory\SynchronousMessageDispatcherFactory;
use Andreo\EventSauceBundle\Factory\UpcasterChainFactory;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\MessageDispatcherChain;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\Upcasting\Upcaster;
use PHPUnit\Framework\TestCase;
use Tests\Factory\Dummy\DummyMessageConsumer;
use Tests\Factory\Dummy\DummyMessageDecorator;
use Tests\Factory\Dummy\DummyMessageDispatcher;
use Tests\Factory\Dummy\DummyMessageUpcaster;
use Tests\Factory\Dummy\DummyUpcaster;

final class FactoryTest extends TestCase
{
    /**
     * @test
     */
    public function should_create_message_decorator_chain(): void
    {
        $chain = MessageDecoratorChainFactory::create([new DummyMessageDecorator(), new DummyMessageDecorator()]);
        $this->assertInstanceOf(MessageDecoratorChain::class, $chain);
    }

    /**
     * @test
     */
    public function should_create_message_dispatcher_chain(): void
    {
        $chain = MessageDispatcherChainFactory::create([new DummyMessageDispatcher(), new DummyMessageDispatcher()]);
        $this->assertInstanceOf(MessageDispatcherChain::class, $chain);
    }

    /**
     * @test
     */
    public function should_create_synchronous_message_dispatcher_chain(): void
    {
        $dispatcher = SynchronousMessageDispatcherFactory::create([new DummyMessageConsumer(), new DummyMessageConsumer()]);
        $this->assertInstanceOf(SynchronousMessageDispatcher::class, $dispatcher);
    }

    /**
     * @test
     */
    public function should_create_upcaster_chain(): void
    {
        $chain = UpcasterChainFactory::create([new DummyUpcaster(), new DummyUpcaster()]);
        $this->assertInstanceOf(Upcaster::class, $chain);
    }

    /**
     * @test
     */
    public function should_create_message_upcaster_chain(): void
    {
        $chain = MessageUpcasterChainFactory::create([new DummyMessageUpcaster(), new DummyMessageUpcaster()]);
        $this->assertInstanceOf(MessageUpcaster::class, $chain);
    }
}
