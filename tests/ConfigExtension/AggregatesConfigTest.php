<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauce\Outbox\AggregateRootRepositoryWithoutDispatchMessage;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithSnapshottingAndStoreStrategy;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\EveryNEventCanStoreSnapshotStrategy;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\Snapshotting\ConstructingAggregateRootRepositoryWithSnapshotting;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineTransactionalMessageRepository;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use LogicException;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tests\ConfigExtension\Dummy\DummyCustomStoreStrategy;
use Tests\ConfigExtension\Dummy\DummyFooAggregate;
use Tests\ConfigExtension\Dummy\DummyFooAggregateWithSnapshotting;
use Tests\ConfigExtension\Dummy\DummyFooAggregateWithVersionedSnapshotting;

final class AggregatesConfigTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }

    /**
     * @test
     */
    public function aggregate_repository_is_loading(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'bazBus',
                        'barBus' => 'xyzBus',
                    ],
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'dispatchers' => ['fooBus', 'barBus'],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_repository.foo');
        $definition = $this->container->getDefinition('andreo.event_sauce.message_repository.foo');
        $this->assertEquals(DoctrineUuidV4MessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher_chain.foo');
        $dispatcherChainDef = $this->container->getDefinition('andreo.event_sauce.message_dispatcher_chain.foo');
        $this->assertInstanceOf(IteratorArgument::class, $argument = $dispatcherChainDef->getArgument(0));
        $this->assertCount(2, $argument->getValues());

        $this->assertContainerBuilderHasAlias('fooRepository');
        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.foo');
        $this->assertEquals(EventSourcedAggregateRootRepository::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function aggregate_repository_with_outbox_is_loading(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'bazBus',
                    ],
                ],
            ],
            'outbox' => [
                'enabled' => true,
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregate::class,
                    'dispatchers' => ['fooBus'],
                    'outbox' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_repository.bar');
        $definition = $this->container->getDefinition('andreo.event_sauce.message_repository.bar');
        $this->assertEquals(DoctrineTransactionalMessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher_chain.bar');
        $dispatcherChainDef = $this->container->getDefinition('andreo.event_sauce.message_dispatcher_chain.bar');
        $this->assertInstanceOf(IteratorArgument::class, $argument = $dispatcherChainDef->getArgument(0));
        $this->assertCount(1, $argument->getValues());

        $this->assertContainerBuilderHasAlias('barRepository');
        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.bar');
        $this->assertEquals(AggregateRootRepositoryWithoutDispatchMessage::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function aggregate_repository_with_snapshotting_is_loading(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
            ],
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'bazBus',
                        'barBus' => 'xyzBus',
                    ],
                ],
            ],
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'dispatchers' => ['barBus'],
                    'snapshot' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_repository.baz');
        $definition = $this->container->getDefinition('andreo.event_sauce.message_repository.baz');
        $this->assertEquals(DoctrineUuidV4MessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher_chain.baz');
        $dispatcherChainDef = $this->container->getDefinition('andreo.event_sauce.message_dispatcher_chain.baz');
        $this->assertInstanceOf(IteratorArgument::class, $argument = $dispatcherChainDef->getArgument(0));
        $this->assertCount(1, $argument->getValues());

        $this->assertContainerBuilderHasAlias('bazRepository');
        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.baz');
        $this->assertEquals(ConstructingAggregateRootRepositoryWithSnapshotting::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function aggregate_repository_with_versioned_snapshotting_is_loading(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'versioned' => true,
            ],
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'bazBus',
                        'barBus' => 'xyzBus',
                    ],
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregateWithVersionedSnapshotting::class,
                    'repository_alias' => 'customNameRepository',
                    'dispatchers' => ['fooBus', 'barBus'],
                    'snapshot' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_repository.foo');
        $definition = $this->container->getDefinition('andreo.event_sauce.message_repository.foo');
        $this->assertEquals(DoctrineUuidV4MessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher_chain.foo');
        $dispatcherChainDef = $this->container->getDefinition('andreo.event_sauce.message_dispatcher_chain.foo');
        $this->assertInstanceOf(IteratorArgument::class, $argument = $dispatcherChainDef->getArgument(0));
        $this->assertCount(2, $argument->getValues());

        $this->assertContainerBuilderHasAlias('customNameRepository');
        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.foo');
        $this->assertEquals(AggregateRootRepositoryWithVersionedSnapshotting::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function aggregate_repository_with_snapshotting_and_every_n_event_store_strategy_is_loading(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'store_strategy' => [
                    'every_n_event' => [
                        'enabled' => true,
                        'number' => 300,
                    ],
                ],
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'snapshot' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('barRepository');
        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.bar');
        $this->assertEquals(AggregateRootRepositoryWithSnapshottingAndStoreStrategy::class, $repositoryDef->getClass());
        /** @var Definition $canStoreDef */
        $canStoreDef = $repositoryDef->getArgument(1);
        $this->assertEquals(EveryNEventCanStoreSnapshotStrategy::class, $canStoreDef->getClass());
    }

    /**
     * @test
     */
    public function aggregate_repository_with_snapshotting_and_custom_store_strategy_is_loading(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'store_strategy' => [
                    'custom' => [
                        'id' => DummyCustomStoreStrategy::class,
                    ],
                ],
            ],
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'snapshot' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('bazRepository');
        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.baz');
        $this->assertEquals(AggregateRootRepositoryWithSnapshottingAndStoreStrategy::class, $repositoryDef->getClass());
        /** @var Reference $canStoreDef */
        $canStoreDef = $repositoryDef->getArgument(1);
        $this->assertEquals(DummyCustomStoreStrategy::class, $canStoreDef->__toString());
    }

    /**
     * @test
     */
    public function aggregate_repository_throw_exception_if_dispatcher_not_be_configured(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'bazBus',
                    ],
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'dispatchers' => ['bazBus'],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function aggregate_repository_throw_exception_if_snapshot_enabled_but_not_enabled_in_root_node(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'snapshot' => [
                'enabled' => false,
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'snapshot' => true,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function aggregate_repository_throw_exception_if_upcast_enabled_but_not_enabled_in_root_node(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'upcast' => [
                'enabled' => false,
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'upcast' => true,
                ],
            ],
        ]);
    }
}
