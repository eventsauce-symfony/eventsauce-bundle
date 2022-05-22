<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauce\Outbox\EventSourcedAggregateRootRepositoryForOutbox;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithSnapshottingAndStoreStrategy;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\DoctrineSnapshotRepository;
use Andreo\EventSauce\Snapshotting\EveryNEventCanStoreSnapshotStrategy;
use Andreo\EventSauce\Upcasting\UpcastingMessageObjectSerializer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Snapshotting\ConstructingAggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\InMemorySnapshotRepository;
use EventSauce\EventSourcing\Upcasting\UpcastingMessageSerializer;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineOutboxRepository;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineTransactionalMessageRepository;
use EventSauce\MessageOutbox\InMemoryOutboxRepository;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Tests\Dummy\DummyFooAggregate;
use Tests\Dummy\DummyFooAggregateWithSnapshotting;
use Tests\Dummy\DummyFooAggregateWithVersionedSnapshotting;
use Tests\Dummy\DummyStoreStrategy;

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
    public function should_load_aggregate_repository(): void
    {
        $this->load([
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.foo');
        $definition = $this->container->getDefinition('andreo.eventsauce.message_repository.foo');
        $this->assertEquals(DoctrineUuidV4MessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasAlias('fooRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_repository.foo');
        $this->assertEquals(EventSourcedAggregateRootRepository::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_load_aggregate_repository_with_empty_dispatchers(): void
    {
        $this->load([
            'synchronous_message_dispatcher' => [
                'chain' => [
                    'fooBus',
                    'barBus',
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_dispatcher_chain.foo');
        $dispatcherChainDef = $this->container->findDefinition('andreo.eventsauce.message_dispatcher_chain.foo');
        $this->assertInstanceOf(IteratorArgument::class, $argument = $dispatcherChainDef->getArgument(0));
        $this->assertCount(2, $argument->getValues());
    }

    /**
     * @test
     */
    public function should_load_aggregate_repository_with_dispatchers(): void
    {
        $this->load([
            'synchronous_message_dispatcher' => [
                'chain' => [
                    'fooBus',
                    'barBus',
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'dispatchers' => ['fooBus'],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_dispatcher_chain.foo');
        $dispatcherChainDef = $this->container->findDefinition('andreo.eventsauce.message_dispatcher_chain.foo');
        $this->assertInstanceOf(IteratorArgument::class, $argument = $dispatcherChainDef->getArgument(0));
        $this->assertCount(1, $argument->getValues());
    }

    /**
     * @test
     */
    public function should_register_outbox_aggregate_repository(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregate::class,
                    'outbox' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.bar');
        $definition = $this->container->getDefinition('andreo.eventsauce.message_repository.bar');
        $this->assertEquals(DoctrineTransactionalMessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasAlias('barRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_repository.bar');
        $this->assertEquals(EventSourcedAggregateRootRepositoryForOutbox::class, $repositoryDef->getClass());

        $outboxProcessMessagesCommand = $this->container->findTaggedServiceIds('andreo.eventsauce.outbox_relay');
        $this->assertArrayHasKey('andreo.eventsauce.outbox_relay.bar', $outboxProcessMessagesCommand);
    }

    /**
     * @test
     */
    public function should_register_outbox_aggregate_repository_with_doctrine_message_repository(): void
    {
        $this->load([
            'outbox' => [
                'repository' => [
                    'doctrine' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregate::class,
                    'outbox' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.bar');
        $aggregateRepositoryDefinition = $this->container->getDefinition('andreo.eventsauce.message_repository.bar');
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
            'outbox' => true,
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregate::class,
                    'outbox' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.bar');
        $aggregateRepositoryDefinition = $this->container->getDefinition('andreo.eventsauce.message_repository.bar');
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
            'outbox' => [
                'repository' => [
                    'memory' => true,
                ],
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregate::class,
                    'outbox' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.bar');
        $aggregateRepositoryDefinition = $this->container->getDefinition('andreo.eventsauce.message_repository.bar');
        /** @var Definition $outboxRepositoryDef */
        $outboxRepositoryDef = $this->container->getDefinition($aggregateRepositoryDefinition->getArgument(1)->__toString());
        $this->assertEquals(InMemoryOutboxRepository::class, $outboxRepositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_outbox_is_enabled_but_root_outbox_option_is_disabled(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'outbox' => false,
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
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'snapshot' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.baz');
        $definition = $this->container->getDefinition('andreo.eventsauce.message_repository.baz');
        $this->assertEquals(DoctrineUuidV4MessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasAlias('bazRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_repository.baz');
        $this->assertEquals(ConstructingAggregateRootRepositoryWithSnapshotting::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_memory_snapshot_aggregate_repository(): void
    {
        $this->load([
            'snapshot' => [
                'repository' => [
                    'memory' => true,
                ],
            ],
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'snapshot' => true,
                ],
            ],
        ]);

        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_repository.baz');
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
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'snapshot' => true,
                ],
            ],
        ]);

        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_repository.foo');
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
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregateWithVersionedSnapshotting::class,
                    'repository_alias' => 'customNameRepository',
                    'snapshot' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.foo');
        $definition = $this->container->getDefinition('andreo.eventsauce.message_repository.foo');
        $this->assertEquals(DoctrineUuidV4MessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasAlias('customNameRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_repository.foo');
        $this->assertEquals(AggregateRootRepositoryWithVersionedSnapshotting::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_snapshot_aggregate_repository_with_every_n_event_strategy(): void
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
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_repository.bar');
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
                        'id' => DummyStoreStrategy::class,
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
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_repository.baz');
        $this->assertEquals(AggregateRootRepositoryWithSnapshottingAndStoreStrategy::class, $repositoryDef->getClass());
        /** @var Reference $canStoreDef */
        $canStoreDef = $repositoryDef->getArgument(1);
        $this->assertEquals(DummyStoreStrategy::class, $canStoreDef->__toString());
    }

    /**
     * @test
     */
    public function should_register_message_repository_with_upcaster_payload_argument(): void
    {
        $this->load([
            'upcaster' => [
                'argument' => 'payload',
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'upcaster' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.foo');
        $messageRepositoryDef = $this->container->getDefinition('andreo.eventsauce.message_repository.foo');
        /** @var Definition $messageSerializerArgument */
        $messageSerializerArgument = $messageRepositoryDef->getArgument(2);
        $this->assertEquals(UpcastingMessageSerializer::class, $messageSerializerArgument->getClass());
    }

    /**
     * @test
     */
    public function should_register_message_repository_with_upcaster_message_argument(): void
    {
        $this->load([
            'upcaster' => [
                'argument' => 'message',
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregate::class,
                    'upcaster' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.bar');
        $messageRepositoryDef = $this->container->getDefinition('andreo.eventsauce.message_repository.bar');
        /** @var Definition $messageSerializerArgument */
        $messageSerializerArgument = $messageRepositoryDef->getArgument(2);
        $this->assertEquals(UpcastingMessageObjectSerializer::class, $messageSerializerArgument->getClass());
    }

    /**
     * @test
     */
    public function should_register_default_serializer_if_upcaster_is_disabled(): void
    {
        $this->load([
            'upcaster' => false,
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregate::class,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.baz');
        $messageRepositoryDef = $this->container->getDefinition('andreo.eventsauce.message_repository.baz');
        /** @var Reference $messageSerializerArgument */
        $messageSerializerArgument = $messageRepositoryDef->getArgument(2);
        $this->assertEquals(MessageSerializer::class, $messageSerializerArgument->__toString());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_dispatcher_is_not_configured(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'synchronous_message_dispatcher' => [
                'chain' => [
                    'fooBus',
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
    public function should_throw_exception_if_aggregate_upcaster_is_enabled_but_root_upcaster_option_is_disabled(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'upcaster' => [
                'enabled' => false,
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'upcaster' => true,
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
