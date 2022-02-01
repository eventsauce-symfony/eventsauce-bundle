<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauce\Messenger\MessengerEventWithHeadersDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageEventDispatcher;
use Andreo\EventSauce\Outbox\AggregateRootRepositoryWithoutDispatchMessage;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithSnapshottingAndStoreStrategy;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\ConstructingSnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\EveryNEventCanStoreSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\BackOff\ExponentialBackOffStrategy;
use EventSauce\BackOff\FibonacciBackOffStrategy;
use EventSauce\BackOff\ImmediatelyFailingBackOffStrategy;
use EventSauce\BackOff\LinearBackOffStrategy;
use EventSauce\BackOff\NoWaitingBackOffStrategy;
use EventSauce\Clock\Clock;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use EventSauce\EventSourcing\Snapshotting\ConstructingAggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\MessageOutbox\DeleteMessageOnCommit;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineTransactionalMessageRepository;
use EventSauce\MessageOutbox\MarkMessagesConsumedOnCommit;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use EventSauce\UuidEncoding\UuidEncoder;
use LogicException;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class EventSauceConfigExtensionTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function time_config_is_loading(): void
    {
        $this->load([
            'time' => [
                'recording_timezone' => 'Europe/Warsaw',
                'clock' => DummyCustomClock::class,
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'andreo.event_sauce.recording_timezone',
            0,
            'Europe/Warsaw'
        );

        $this->assertContainerBuilderHasAlias(Clock::class);
        $clockAlias = $this->container->getAlias(Clock::class);
        $this->assertEquals(DummyCustomClock::class, $clockAlias->__toString());
    }

    /**
     * @test
     */
    public function message_config_is_loading(): void
    {
        $this->load([
            'message' => [
                'repository' => [
                    'doctrine' => [
                        'connection' => 'doctrine.default_connection',
                        'table_schema' => DummyCustomTableSchema::class,
                    ],
                ],
                'serializer' => DummyCustomMessageSerializer::class,
                'decorator' => false,
            ],
        ]);

        $this->assertContainerBuilderHasAlias('andreo.event_sauce.doctrine.connection');
        $connectionAlias = $this->container->getAlias('andreo.event_sauce.doctrine.connection');
        $this->assertEquals('doctrine.default_connection', $connectionAlias->__toString());

        $this->assertContainerBuilderHasAlias(MessageSerializer::class);
        $serializerAlias = $this->container->getAlias(MessageSerializer::class);
        $this->assertEquals(DummyCustomMessageSerializer::class, $serializerAlias->__toString());

        $this->assertArrayNotHasKey(AsMessageDecorator::class, $this->container->getAutoconfiguredAttributes());
    }

    /**
     * @test
     */
    public function messenger_message_dispatcher_of_mode_event_with_headers_is_loading(): void
    {
        $this->load([
            'dispatcher' => [
                'messenger' => [
                    'enabled' => true,
                    'mode' => 'event_with_headers',
                ],
                'chain' => [
                    'fooBus' => 'barBus',
                    'barBus' => 'bazBus',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.event_sauce.message_dispatcher.fooBus',
            'andreo.event_sauce.event_with_headers_dispatcher',
            [
                'bus' => 'barBus',
            ]
        );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(MessengerEventWithHeadersDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('barBus'));

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.event_sauce.message_dispatcher.barBus',
            'andreo.event_sauce.event_with_headers_dispatcher',
            [
                'bus' => 'bazBus',
            ]
        );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.barBus');
        $this->assertEquals(MessengerEventWithHeadersDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('bazBus'));
    }

    /**
     * @test
     */
    public function messenger_message_dispatcher_of_mode_event_is_loading(): void
    {
        $this->load([
            'dispatcher' => [
                'messenger' => [
                    'enabled' => true,
                    'mode' => 'event',
                ],
                'chain' => [
                    'fooBus' => 'barBus',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.fooBus', );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(MessengerMessageEventDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('barBus'));
    }

    /**
     * @test
     */
    public function messenger_message_dispatcher_of_mode_message_is_loading(): void
    {
        $this->load([
            'dispatcher' => [
                'messenger' => [
                    'enabled' => true,
                    'mode' => 'message',
                ],
                'chain' => [
                    'fooBus' => 'bazBus',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.fooBus', );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(MessengerMessageDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('bazBus'));
    }

    /**
     * @test
     */
    public function default_message_dispatcher_config_is_loading(): void
    {
        $this->load([
            'dispatcher' => [
                'chain' => ['fooBus', 'barBus'],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('fooBus');
        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.fooBus');
        $dispatcherDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(SynchronousMessageDispatcher::class, $dispatcherDefinition->getClass());

        $this->assertEquals(
            $dispatcherDefinition->getArgument(0),
            new TaggedIteratorArgument('andreo.event_sauce.message_consumer.fooBus')
        );

        $this->assertContainerBuilderHasAlias('barBus');
        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.barBus');
        $dispatcherDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.barBus');
        $this->assertEquals(SynchronousMessageDispatcher::class, $dispatcherDefinition->getClass());
        $this->assertEquals(
            $dispatcherDefinition->getArgument(0),
            new TaggedIteratorArgument('andreo.event_sauce.message_consumer.barBus')
        );
    }

    /**
     * @test
     */
    public function messenger_message_dispatcher_config_throw_exception_if_bus_not_defined(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'dispatcher' => [
                'messenger' => [
                    'enabled' => true,
                    'mode' => 'message',
                ],
                'chain' => [
                    'fooBus' => null,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function message_decorator_config_is_loading(): void
    {
        $this->load([
            'message' => [
                'decorator' => false,
            ],
        ]);

        $this->assertArrayNotHasKey(AsMessageDecorator::class, $this->container->getAutoconfiguredAttributes());
    }

    /**
     * @test
     */
    public function outbox_exponential_back_of_strategy_is_loading(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'exponential' => [
                        'enabled' => true,
                        'initial_delay_ms' => 200000,
                        'max_tries' => 20,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(ExponentialBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $exponentialDefinition = $this->container->getDefinition(ExponentialBackOffStrategy::class);
        $this->assertEquals(200000, $exponentialDefinition->getArgument(0));
        $this->assertEquals(20, $exponentialDefinition->getArgument(1));
    }

    /**
     * @test
     */
    public function outbox_fibonacci_back_of_strategy_is_loading(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'fibonacci' => [
                        'enabled' => true,
                        'max_tries' => 30,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(FibonacciBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $fibonacciDefinition = $this->container->getDefinition(FibonacciBackOffStrategy::class);
        $this->assertEquals(
            '%andreo.event_sauce.outbox.back_off.initial_delay_ms%',
            $fibonacciDefinition->getArgument(0)
        );
        $this->assertEquals(30, $fibonacciDefinition->getArgument(1));
    }

    /**
     * @test
     */
    public function outbox_linear_back_of_strategy_is_loading(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'linear' => [
                        'enabled' => true,
                        'initial_delay_ms' => 300000,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(LinearBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $linearDefinition = $this->container->getDefinition(LinearBackOffStrategy::class);
        $this->assertEquals(300000, $linearDefinition->getArgument(0));
        $this->assertEquals('%andreo.event_sauce.outbox.back_off.max_tries%', $linearDefinition->getArgument(1));
    }

    /**
     * @test
     */
    public function outbox_no_waiting_back_of_strategy_is_loading(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'no_waiting' => [
                        'enabled' => true,
                        'max_tries' => 20,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(NoWaitingBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $noWaitingDefinition = $this->container->getDefinition(NoWaitingBackOffStrategy::class);
        $this->assertEquals(20, $noWaitingDefinition->getArgument(0));
    }

    /**
     * @test
     */
    public function outbox_immediately_back_of_strategy_is_loading(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'immediately' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(ImmediatelyFailingBackOffStrategy::class, $backOffStrategyAlias->__toString());
    }

    /**
     * @test
     */
    public function outbox_custom_back_of_strategy_is_loading(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'custom' => [
                        'id' => DummyCustomBackOfStrategy::class,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(DummyCustomBackOfStrategy::class, $backOffStrategyAlias->__toString());
    }

    /**
     * @test
     */
    public function outbox_back_of_strategy_config_throw_error_if_more_than_one_strategy_selected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'exponential' => [
                        'enabled' => true,
                        'initial_delay_ms' => 200000,
                        'max_tries' => 20,
                    ],
                    'no_waiting' => [
                        'enabled' => true,
                        'max_tries' => 20,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function outbox_delete_relay_commit_strategy_is_loading(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'relay_commit' => [
                    'delete' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(RelayCommitStrategy::class);
        $relayCommitStrategyAlias = $this->container->getAlias(RelayCommitStrategy::class);
        $this->assertEquals(DeleteMessageOnCommit::class, $relayCommitStrategyAlias->__toString());
    }

    /**
     * @test
     */
    public function outbox_mark_consumed_relay_commit_strategy_is_loading(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'relay_commit' => [
                    'mark_consumed' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(RelayCommitStrategy::class);
        $relayCommitStrategyAlias = $this->container->getAlias(RelayCommitStrategy::class);
        $this->assertEquals(MarkMessagesConsumedOnCommit::class, $relayCommitStrategyAlias->__toString());
    }

    /**
     * @test
     */
    public function outbox_relay_commit_strategy_config_throw_error_if_more_than_one_strategy_selected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'outbox' => [
                'enabled' => true,
                'relay_commit' => [
                    'delete' => [
                        'enabled' => true,
                    ],
                    'mark_consumed' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function snapshot_state_serializer_is_loading(): void
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
        ]);

        $this->assertContainerBuilderHasAlias(SnapshotStateSerializer::class);
        $snapshotSerializerAlias = $this->container->getAlias(SnapshotStateSerializer::class);
        $this->assertEquals(ConstructingSnapshotStateSerializer::class, $snapshotSerializerAlias->__toString());
    }

    /**
     * @test
     */
    public function custom_snapshot_state_serializer_is_loading(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'repository' => [
                    'doctrine' => [
                        'enabled' => true,
                    ],
                ],
                'serializer' => DummySnapshotStateSerializer::class,
            ],
        ]);

        $this->assertContainerBuilderHasAlias(SnapshotStateSerializer::class);
        $snapshotSerializerAlias = $this->container->getAlias(SnapshotStateSerializer::class);
        $this->assertEquals(DummySnapshotStateSerializer::class, $snapshotSerializerAlias->__toString());
    }

    /**
     * @test
     */
    public function snapshot_state_serializer_is_not_loading(): void
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
        ]);

        $this->assertContainerBuilderNotHasService(ConstructingSnapshotStateSerializer::class);
    }

    /**
     * @test
     */
    public function upcast_config_is_loading(): void
    {
        $this->load([
            'upcast' => [
                'enabled' => true,
            ],
        ]);

        $this->assertArrayHasKey(AsUpcaster::class, $this->container->getAutoconfiguredAttributes());
    }

    /**
     * @test
     */
    public function custom_payload_serializer_is_loading(): void
    {
        $this->load([
            'payload_serializer' => DummyCustomPayloadSerializer::class,
        ]);

        $this->assertContainerBuilderHasAlias(PayloadSerializer::class);
        $payloadSerializerAlias = $this->container->getAlias(PayloadSerializer::class);
        $this->assertEquals(DummyCustomPayloadSerializer::class, $payloadSerializerAlias->__toString());
    }

    /**
     * @test
     */
    public function custom_uuid_encoder_is_loading(): void
    {
        $this->load([
            'uuid_encoder' => DummyUuidEncoder::class,
        ]);

        $this->assertContainerBuilderHasAlias(UuidEncoder::class);
        $uuidEncoderAlias = $this->container->getAlias(UuidEncoder::class);
        $this->assertEquals(DummyUuidEncoder::class, $uuidEncoderAlias->__toString());
    }

    /**
     * @test
     */
    public function custom_class_name_inflector_is_loading(): void
    {
        $this->load([
            'class_name_inflector' => DummyClassNameInflector::class,
        ]);

        $this->assertContainerBuilderHasAlias(ClassNameInflector::class);
        $classNameInflectorAlias = $this->container->getAlias(ClassNameInflector::class);
        $this->assertEquals(DummyClassNameInflector::class, $classNameInflectorAlias->__toString());
    }

    /**
     * @test
     */
    public function aggregate_repository_is_loading(): void
    {
        $this->load([
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
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'message' => [
                        'dispatchers' => ['fooBus', 'barBus'],
                    ],
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
            'dispatcher' => [
                'messenger' => [
                    'enabled' => true,
                    'mode' => 'event',
                ],
                'chain' => [
                    'fooBus' => 'bazBus',
                ],
            ],
            'outbox' => [
                'enabled' => true,
            ],
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregate::class,
                    'message' => [
                        'dispatchers' => ['fooBus'],
                        'outbox' => true,
                    ],
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
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'message' => [
                        'dispatchers' => ['barBus'],
                    ],
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
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregateWithVersionedSnapshotting::class,
                    'repository_alias' => 'customNameRepository',
                    'message' => [
                        'dispatchers' => ['fooBus', 'barBus'],
                    ],
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
            'dispatcher' => [
                'messenger' => [
                    'enabled' => true,
                    'mode' => 'event',
                ],
                'chain' => [
                    'fooBus' => 'bazBus',
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'message' => [
                        'dispatchers' => ['bazBus'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function aggregate_repository_throw_exception_if_decorator_enabled_but_not_enabled_in_root_node(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'message' => [
                'decorator' => false,
            ],
            'dispatcher' => [
                'messenger' => [
                    'enabled' => true,
                    'mode' => 'event',
                ],
                'chain' => [
                    'fooBus' => 'bazBus',
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregate::class,
                    'message' => [
                        'dispatchers' => ['fooBus'],
                        'decorator' => true,
                    ],
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
                    'message' => [
                        'decorator' => true,
                    ],
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
                    'message' => [
                        'decorator' => true,
                    ],
                    'upcast' => true,
                ],
            ],
        ]);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
