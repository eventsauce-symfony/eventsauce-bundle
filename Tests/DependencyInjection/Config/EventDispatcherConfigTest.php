<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\EventDispatcher;
use EventSauce\EventSourcing\MessageDispatchingEventDispatcher;
use EventSauce\MessageOutbox\OutboxMessageDispatcher;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Definition;

final class EventDispatcherConfigTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_default_event_dispatcher_config(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(MessageDispatchingEventDispatcher::class);
        $this->assertContainerBuilderNotHasService(EventDispatcher::class);
    }

    /**
     * @test
     */
    public function should_load_event_dispatcher_config(): void
    {
        $this->load([
            'event_dispatcher' => true,
        ]);

        $this->assertContainerBuilderHasService(MessageDispatchingEventDispatcher::class);
        $this->assertContainerBuilderHasAlias(EventDispatcher::class);
    }

    /**
     * @test
     */
    public function should_load_event_dispatcher_with_outbox(): void
    {
        $this->load([
            'message_outbox' => true,
            'event_dispatcher' => [
                'message_outbox' => true,
            ],
        ]);

        $this->assertContainerBuilderHasService(MessageDispatchingEventDispatcher::class);
        $this->assertContainerBuilderHasAlias(EventDispatcher::class);

        $dispatcherDef = $this->container->findDefinition(MessageDispatchingEventDispatcher::class);
        /** @var Definition $messageDispatcherArg */
        $messageDispatcherArg = $dispatcherDef->getArgument(0);
        $this->assertEquals(OutboxMessageDispatcher::class, $messageDispatcherArg->getClass());

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.eventsauce.event_dispatcher.outbox_relay',
            'andreo.eventsauce.outbox_relay',
            [
                'relay_id' => 'event_dispatcher_relay',
            ]
        );
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
