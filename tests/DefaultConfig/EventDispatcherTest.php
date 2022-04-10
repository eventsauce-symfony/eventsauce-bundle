<?php

declare(strict_types=1);

namespace Tests\DefaultConfig;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\EventDispatcher;
use EventSauce\EventSourcing\MessageDispatchingEventDispatcher;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineOutboxRepository;
use EventSauce\MessageOutbox\InMemoryOutboxRepository;
use EventSauce\MessageOutbox\OutboxMessageDispatcher;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class EventDispatcherTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_event_dispatcher(): void
    {
        $this->load([
            'event_dispatcher' => true,
        ]);

        $this->assertContainerBuilderHasService(MessageDispatchingEventDispatcher::class);
        $this->assertContainerBuilderHasAlias(EventDispatcher::class);

        $dispatcherDef = $this->container->findDefinition(MessageDispatchingEventDispatcher::class);
        /** @var Reference $messageDispatcherArg */
        $messageDispatcherArg = $dispatcherDef->getArgument(0);
        $this->assertEquals('andreo.eventsauce.message_dispatcher_chain', $messageDispatcherArg->__toString());
    }

    /**
     * @test
     */
    public function should_load_event_dispatcher_with_outbox(): void
    {
        $this->load([
            'outbox' => true,
            'event_dispatcher' => [
                'outbox' => true,
            ],
        ]);

        $this->assertContainerBuilderHasService(MessageDispatchingEventDispatcher::class);
        $this->assertContainerBuilderHasAlias(EventDispatcher::class);

        $dispatcherDef = $this->container->findDefinition(MessageDispatchingEventDispatcher::class);
        /** @var Definition $messageDispatcherArg */
        $messageDispatcherArg = $dispatcherDef->getArgument(0);
        $this->assertEquals(OutboxMessageDispatcher::class, $messageDispatcherArg->getClass());
        /** @var Definition $repositoryArg */
        $repositoryArg = $messageDispatcherArg->getArgument(0);
        $this->assertEquals(DoctrineOutboxRepository::class, $repositoryArg->getClass());
    }

    /**
     * @test
     */
    public function should_load_event_dispatcher_with_outbox_of_memory(): void
    {
        $this->load([
            'outbox' => true,
            'event_dispatcher' => [
                'outbox' => [
                    'repository' => [
                        'memory' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService(MessageDispatchingEventDispatcher::class);
        $this->assertContainerBuilderHasAlias(EventDispatcher::class);

        $dispatcherDef = $this->container->findDefinition(MessageDispatchingEventDispatcher::class);
        /** @var Definition $messageDispatcherArg */
        $messageDispatcherArg = $dispatcherDef->getArgument(0);
        $this->assertEquals(OutboxMessageDispatcher::class, $messageDispatcherArg->getClass());
        /** @var Definition $repositoryArg */
        $repositoryArg = $messageDispatcherArg->getArgument(0);
        $this->assertEquals(InMemoryOutboxRepository::class, $repositoryArg->getClass());
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
