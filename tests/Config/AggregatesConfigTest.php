<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauce\Outbox\AggregateRootRepositoryWithoutDispatchMessage;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithSnapshottingAndStoreStrategy;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\DoctrineSnapshotRepository;
use Andreo\EventSauce\Snapshotting\EveryNEventCanStoreSnapshotStrategy;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\Snapshotting\ConstructingAggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\InMemorySnapshotRepository;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineOutboxRepository;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineTransactionalMessageRepository;
use EventSauce\MessageOutbox\InMemoryOutboxRepository;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use LogicException;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tests\Config\Dummy\DummyCustomStoreStrategy;
use Tests\Config\Dummy\DummyFooAggregate;
use Tests\Config\Dummy\DummyFooAggregateWithSnapshotting;
use Tests\Config\Dummy\DummyFooAggregateWithVersionedSnapshotting;

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
    public function should_register_default_aggregate_repository(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'dispatchers' => ['fooBus', 'bazBus'],
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
    public function should_register_default_aggregate_repository_with_empty_dispatchers(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregate::class,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher_chain.baz');
        $dispatcherChainDef = $this->container->getDefinition('andreo.event_sauce.message_dispatcher_chain.baz');
        $this->assertInstanceOf(IteratorArgument::class, $argument = $dispatcherChainDef->getArgument(0));
        $this->assertCount(2, $argument->getValues());
    }

    /**
     * @test
     */
    public function should_register_outbox_aggregate_repository(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
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

        $outboxProcessMessagesCommand = $this->container->findTaggedServiceIds('andreo.event_sauce.outbox_relay');
        $this->assertArrayHasKey('andreo.event_sauce.outbox_relay.bar', $outboxProcessMessagesCommand);
    }

    /**
     * @test
     */
    public function should_register_doctrine_outbox_aggregate_repository(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                    ],
                ],
            ],
            'outbox' => [
                'enabled' => true,
                'repository' => [
                    'doctrine' => [
                        'enabled' => true,
                    ],
                ],
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
        $aggregateRepositoryDefinition = $this->container->getDefinition('andreo.event_sauce.message_repository.bar');
        /** @var Definition $outboxRepositoryDef */
        $outboxRepositoryDef = $this->container->getDefinition($aggregateRepositoryDefinition->getArgument(2)->__toString());
        $this->assertEquals(DoctrineOutboxRepository::class, $outboxRepositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_doctrine_outbox_aggregate_repository_as_default(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
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
        $aggregateRepositoryDefinition = $this->container->getDefinition('andreo.event_sauce.message_repository.bar');
        /** @var Definition $outboxRepositoryDef */
        $outboxRepositoryDef = $this->container->getDefinition($aggregateRepositoryDefinition->getArgument(2)->__toString());
        $this->assertEquals(DoctrineOutboxRepository::class, $outboxRepositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_memory_outbox_aggregate_repository(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                    ],
                ],
            ],
            'outbox' => [
                'enabled' => true,
                'repository' => [
                    'memory' => [
                        'enabled' => true,
                    ],
                ],
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
        $aggregateRepositoryDefinition = $this->container->getDefinition('andreo.event_sauce.message_repository.bar');
        /** @var Definition $outboxRepositoryDef */
        $outboxRepositoryDef = $this->container->getDefinition($aggregateRepositoryDefinition->getArgument(2)->__toString());
        $this->assertEquals(InMemoryOutboxRepository::class, $outboxRepositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_outbox_is_enabled_but_root_outbox_option_is_disabled(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'outbox' => [
                'enabled' => false,
            ],
            'aggregates' => [
                'qux' => [
                    'class' => DummyFooAggregate::class,
                    'outbox' => true,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_register_snapshot_aggregate_repository(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
            ],
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'dispatchers' => ['fooBus'],
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
    public function should_register_memory_snapshot_aggregate_repository(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'repository' => [
                    'memory' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'dispatchers' => ['fooBus'],
                    'snapshot' => true,
                ],
            ],
        ]);

        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.baz');
        /** @var Definition $snapshotRepositoryDef */
        $snapshotRepositoryDef = $repositoryDef->getArgument(2);
        $this->assertEquals(InMemorySnapshotRepository::class, $snapshotRepositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_memory_snapshot_aggregate_repository_as_default(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
            ],
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'dispatchers' => ['fooBus'],
                    'snapshot' => true,
                ],
            ],
        ]);

        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.bar');
        /** @var Definition $snapshotRepositoryDef */
        $snapshotRepositoryDef = $repositoryDef->getArgument(2);
        $this->assertEquals(InMemorySnapshotRepository::class, $snapshotRepositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_doctrine_snapshot_aggregate_repository(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'repository' => [
                    'doctrine' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'dispatchers' => ['fooBus'],
                    'snapshot' => true,
                ],
            ],
        ]);

        $repositoryDef = $this->container->getDefinition('andreo.event_sauce.aggregate_repository.foo');
        /** @var Definition $snapshotRepositoryDef */
        $snapshotRepositoryDef = $repositoryDef->getArgument(2);
        $this->assertEquals(DoctrineSnapshotRepository::class, $snapshotRepositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_snapshot_is_enabled_but_root_snapshot_option_is_disabled(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'snapshot' => [
                'enabled' => false,
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregate::class,
                    'snapshot' => true,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_register_versioned_snapshot_aggregate_repository(): void
    {
        $this->load([
            'snapshot' => [
                'versioned' => true,
            ],
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregateWithVersionedSnapshotting::class,
                    'repository_alias' => 'customNameRepository',
                    'dispatchers' => ['fooBus', 'bazBus'],
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
    public function should_register_snapshot_aggregate_repository_with_every_n_event_store_strategy(): void
    {
        $this->load([
            'snapshot' => [
                'store_strategy' => [
                    'every_n_event' => [
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
    public function should_register_snapshot_aggregate_repository_with_custom_store_strategy(): void
    {
        $this->load([
            'snapshot' => [
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
    public function should_throw_exception_if_aggregate_dispatcher_is_not_configured(): void
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
    public function should_throw_exception_if_aggregate_upcast_is_enabled_but_root_upcast_option_is_disabled(): void
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

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_name_is_not_string(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'aggregates' => [
                0 => [
                    'class' => DummyFooAggregate::class,
                ],
            ],
        ]);
    }
}
