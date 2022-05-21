<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Outbox\EventSourcedAggregateRootRepositoryForOutbox;
use Andreo\EventSauce\Outbox\ForwardingMessageConsumer;
use Andreo\EventSauce\Outbox\InMemoryTransactionalMessageRepository;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithSnapshottingAndStoreStrategy;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\DoctrineSnapshotRepository;
use Andreo\EventSauce\Snapshotting\EveryNEventCanStoreSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauce\Upcasting\MessageUpcasterChain;
use Andreo\EventSauce\Upcasting\UpcasterChainWithEventGuessing;
use Andreo\EventSauce\Upcasting\UpcastingMessageObjectSerializer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Factory\MessageDispatcherChainFactory;
use Andreo\EventSauceBundle\Factory\MessageUpcasterChainFactory;
use Andreo\EventSauceBundle\Factory\UpcasterChainFactory;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\EventSourcing\AggregateRootRepository;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\InMemoryMessageRepository;
use EventSauce\EventSourcing\MessageDispatcherChain;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\ConstructingAggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\InMemorySnapshotRepository;
use EventSauce\EventSourcing\Upcasting\UpcasterChain;
use EventSauce\EventSourcing\Upcasting\UpcastingMessageSerializer;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineOutboxRepository;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineTransactionalMessageRepository;
use EventSauce\MessageOutbox\InMemoryOutboxRepository;
use EventSauce\MessageOutbox\OutboxRelay;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use EventSauce\UuidEncoding\UuidEncoder;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

final class AggregatesLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $messageConfig = $config['event_store'];
        $snapshotConfig = $config['snapshot'];
        $upcasterConfig = $config['upcaster'];

        foreach ($config['aggregates'] as $aggregateName => $aggregateConfig) {
            $aggregateConfig['repository_alias'] ??= sprintf('%sRepository', $aggregateName);

            $this->loadAggregateDispatchers(
                $aggregateName,
                $aggregateConfig,
                $config,
            );

            $this->loadAggregateMessageRepository(
                $aggregateName,
                $aggregateConfig,
                $messageConfig,
                $upcasterConfig
            );

            $this->loadAggregateRepository(
                $aggregateName,
                $aggregateConfig
            );

            if ($this->extension->isConfigEnabled($this->container, $aggregateConfig['outbox'])) {
                $this->loadAggregateOutboxRepository(
                    $aggregateName,
                    $aggregateConfig,
                    $config['outbox']
                );
            }

            if ($this->extension->isConfigEnabled($this->container, $aggregateConfig['snapshot'])) {
                $this->loadAggregateSnapshotRepository(
                    $aggregateName,
                    $aggregateConfig,
                    $snapshotConfig
                );
            }
        }
    }

    private function loadAggregateDispatchers(
        string $aggregateName,
        array $aggregateConfig,
        array $config
    ): void {
        $aggregateDispatchers = $aggregateConfig['dispatchers'];

        if (empty($aggregateDispatchers)) {
            $this->container->setAlias(
                "andreo.eventsauce.message_dispatcher_chain.$aggregateName",
                'andreo.eventsauce.message_dispatcher_chain'
            );
        } else {
            $synchronousMessageDispatcher = $config['synchronous_message_dispatcher'];
            $messengerMessageDispatcher = $config['messenger_message_dispatcher'];
            $messageDispatcherChainConfig = [];
            if ($this->extension->isConfigEnabled($this->container, $synchronousMessageDispatcher)) {
                $messageDispatcherChainConfig = $synchronousMessageDispatcher['chain'];
            } elseif ($this->extension->isConfigEnabled($this->container, $messengerMessageDispatcher)) {
                $messageDispatcherChainConfig = $messengerMessageDispatcher['chain'];
            }

            $messageDispatcherRefers = [];
            foreach ($aggregateDispatchers as $aggregateDispatcherAlias) {
                if (!array_key_exists($aggregateDispatcherAlias, $messageDispatcherChainConfig)) {
                    throw new LogicException(sprintf('Message dispatcher with name "%s" is not configured. Configure it in the message section.', $aggregateDispatcherAlias));
                }
                $messageDispatcherRefers[] = new Reference("andreo.eventsauce.message_dispatcher.$aggregateDispatcherAlias");
            }

            $this->container
                ->register(
                    "andreo.eventsauce.message_dispatcher_chain.$aggregateName",
                    MessageDispatcherChain::class
                )
                ->setFactory([MessageDispatcherChainFactory::class, 'create'])
                ->addArgument(new IteratorArgument($messageDispatcherRefers))
                ->setPublic(false)
            ;
        }
    }

    private function loadMessageSerializer(
        string $aggregateName,
        array $aggregateConfig,
        array $upcasterConfig
    ): Definition|Reference {
        if ($this->extension->isConfigEnabled($this->container, $aggregateConfig['upcaster'])) {
            if (!$this->extension->isConfigEnabled($this->container, $upcasterConfig)) {
                throw new LogicException('Upcast config is disabled. If you want to use it, enable and configure it .');
            }

            $context = $upcasterConfig['argument'];
            if ('payload' === $context) {
                if (!class_exists(UpcasterChainWithEventGuessing::class)) {
                    $upcasterChainDef = (new Definition(UpcasterChain::class, [
                        new TaggedIteratorArgument("andreo.eventsauce.upcaster.$aggregateName"),
                    ]))->setFactory([UpcasterChainFactory::class, 'create']);
                } else {
                    $upcasterChainDef = new Definition(UpcasterChainWithEventGuessing::class, [
                        new TaggedIteratorArgument("andreo.eventsauce.upcaster.$aggregateName"),
                        new Reference(ClassNameInflector::class),
                    ]);
                }

                $messageSerializerArgument = new Definition(UpcastingMessageSerializer::class, [
                    new Reference(MessageSerializer::class),
                    $upcasterChainDef,
                ]);
            } else {
                $upcasterChainDef = (new Definition(MessageUpcasterChain::class, [
                    new TaggedIteratorArgument("andreo.eventsauce.upcaster.$aggregateName"),
                ]))->setFactory([MessageUpcasterChainFactory::class, 'create']);

                $messageSerializerArgument = new Definition(UpcastingMessageObjectSerializer::class, [
                    new Reference(MessageSerializer::class),
                    $upcasterChainDef,
                ]);
            }
        } else {
            $messageSerializerArgument = new Reference(MessageSerializer::class);
        }

        return $messageSerializerArgument;
    }

    private function loadAggregateMessageRepository(
        string $aggregateName,
        array $aggregateConfig,
        array $messageConfig,
        array $upcasterConfig
    ): void {
        $messageRepositoryConfig = $messageConfig['repository'];
        $messageRepositoryMemoryEnabled = $this->extension->isConfigEnabled(
            $this->container,
            $messageRepositoryConfig['memory']
        );

        if (!$messageRepositoryMemoryEnabled) {
            $messageRepositoryDoctrineConfig = $messageRepositoryConfig['doctrine'];
            $jsonEncodeOptions = $messageRepositoryDoctrineConfig['json_encode_options'];
            $messageTableName = $messageRepositoryDoctrineConfig['table_name'];
            $tableName = sprintf('%s_%s', $aggregateName, $messageTableName);
            $messageSerializerArgument = $this->loadMessageSerializer(
                $aggregateName,
                $aggregateConfig,
                $upcasterConfig
            );

            $this->container
                ->register("andreo.eventsauce.message_repository.$aggregateName", DoctrineUuidV4MessageRepository::class)
                ->setArguments([
                    new Reference('andreo.eventsauce.doctrine.connection'),
                    $tableName,
                    $messageSerializerArgument,
                    array_reduce($jsonEncodeOptions, static fn (int $a, int $b) => $a | $b, 0),
                    new Reference(TableSchema::class),
                    new Reference(UuidEncoder::class),
                ])
                ->setPublic(false)
            ;
        } else {
            $this->container
                ->register("andreo.eventsauce.message_repository.$aggregateName", InMemoryMessageRepository::class)
                ->setPublic(false);
        }
    }

    private function loadAggregateRepository(
        string $aggregateName,
        array $aggregateConfig
    ): void {
        $aggregateClass = $aggregateConfig['class'];
        $repositoryAlias = $aggregateConfig['repository_alias'];

        $this->container
            ->register("andreo.eventsauce.aggregate_repository.$aggregateName", EventSourcedAggregateRootRepository::class)
            ->setArguments([
                $aggregateClass,
                new Reference("andreo.eventsauce.message_repository.$aggregateName"),
                new Reference("andreo.eventsauce.message_dispatcher_chain.$aggregateName"),
                new Reference('andreo.eventsauce.message_decorator_chain'),
                new Reference(ClassNameInflector::class),
            ])
            ->setPublic(false)
        ;

        $this->container->setAlias($repositoryAlias, "andreo.eventsauce.aggregate_repository.$aggregateName");
        $this->container->registerAliasForArgument($repositoryAlias, AggregateRootRepository::class);
    }

    private function loadAggregateOutboxRepository(
        string $aggregateName,
        array $aggregateConfig,
        array $outboxConfig
    ): void {
        $outboxRepositoryConfig = $outboxConfig['repository'];
        $repositoryAlias = $aggregateConfig['repository_alias'];
        $aggregateClass = $aggregateConfig['class'];

        if (!$this->extension->isConfigEnabled($this->container, $outboxConfig)) {
            throw new LogicException('Message default outbox config is disabled. If you want to use it, enable and configure it .');
        }

        $memoryRepositoryEnabled = $this->extension->isConfigEnabled(
            $this->container,
            $outboxRepositoryConfig['memory']
        );

        if (!$memoryRepositoryEnabled) {
            $outboxRepositoryDoctrineConfig = $outboxRepositoryConfig['doctrine'];
            $tableName = sprintf('%s_%s', $aggregateName, $outboxRepositoryDoctrineConfig['table_name']);
            $this->container
                ->register("andreo.eventsauce.outbox_repository.$aggregateName", DoctrineOutboxRepository::class)
                ->setArguments([
                    new Reference('andreo.eventsauce.doctrine.connection'),
                    $tableName,
                    new Reference(MessageSerializer::class),
                ])
                ->setPublic(false)
            ;

            $regularMessageRepositoryDef = $this->container->getDefinition("andreo.eventsauce.message_repository.$aggregateName");
            $this->container
                ->register("andreo.eventsauce.message_repository.$aggregateName", DoctrineTransactionalMessageRepository::class)
                ->setArguments([
                    new Reference('andreo.eventsauce.doctrine.connection'),
                    $regularMessageRepositoryDef,
                    new Reference("andreo.eventsauce.outbox_repository.$aggregateName"),
                ])
                ->setPublic(false)
            ;
        } else {
            $this->container
                ->register("andreo.eventsauce.outbox_repository.$aggregateName", InMemoryOutboxRepository::class)
                ->setPublic(false)
            ;

            $regularMessageRepositoryDef = $this->container->getDefinition("andreo.eventsauce.message_repository.$aggregateName");
            $this->container
                ->register("andreo.eventsauce.message_repository.$aggregateName", InMemoryTransactionalMessageRepository::class)
                ->setArguments([
                    $regularMessageRepositoryDef,
                    new Reference("andreo.eventsauce.outbox_repository.$aggregateName"),
                ])
                ->setPublic(false)
            ;
        }

        $regularAggregateRepositoryDef = $this->container->getDefinition("andreo.eventsauce.aggregate_repository.$aggregateName");
        $this->container
            ->register("andreo.eventsauce.aggregate_repository.$aggregateName", EventSourcedAggregateRootRepositoryForOutbox::class)
            ->setArguments([
                $aggregateClass,
                new Reference("andreo.eventsauce.message_repository.$aggregateName"),
                $regularAggregateRepositoryDef,
                new Reference('andreo.eventsauce.message_decorator_chain'),
                new Reference(ClassNameInflector::class),
            ])
            ->setPublic(false)
        ;

        $this->container->setAlias($repositoryAlias, "andreo.eventsauce.aggregate_repository.$aggregateName");
        $this->container->registerAliasForArgument($repositoryAlias, AggregateRootRepository::class);

        $messageConsumerDefinition = new Definition(ForwardingMessageConsumer::class, [
            new Reference("andreo.eventsauce.message_dispatcher_chain.$aggregateName"),
        ]);

        $this->container
            ->register("andreo.eventsauce.outbox_relay.$aggregateName", OutboxRelay::class)
            ->setArguments([
                new Reference("andreo.eventsauce.outbox_repository.$aggregateName"),
                $messageConsumerDefinition,
                new Reference(BackOffStrategy::class),
                new Reference(RelayCommitStrategy::class),
            ])
            ->addTag('andreo.eventsauce.outbox_relay', [
                'name' => "outbox_relay_$aggregateName",
            ])
            ->setPublic(false)
        ;
    }

    private function loadAggregateSnapshotRepository(
        string $aggregateName,
        array $aggregateConfig,
        array $snapshotConfig
    ): void {
        if (!$this->extension->isConfigEnabled($this->container, $snapshotConfig)) {
            throw new LogicException(sprintf('To use snapshot for aggregate "%s", you must enable snapshot in main section.', $aggregateName));
        }

        $aggregateClass = $aggregateConfig['class'];
        $snapshotRepositoryConfig = $snapshotConfig['repository'];
        $repositoryAlias = $aggregateConfig['repository_alias'];
        $storeStrategyConfig = $snapshotConfig['store_strategy'];

        $snapshotDoctrineRepositoryEnabled = $this->extension->isConfigEnabled(
            $this->container,
            $snapshotDoctrineRepositoryConfig = $snapshotRepositoryConfig['doctrine']
        );

        if (!$snapshotDoctrineRepositoryEnabled) {
            $snapshotRepositoryDef = new Definition(InMemorySnapshotRepository::class);
        } else {
            $tableName = sprintf('%s_%s', $aggregateName, $snapshotDoctrineRepositoryConfig['table_name']);
            $snapshotRepositoryDef = new Definition(DoctrineSnapshotRepository::class, [
                new Reference('andreo.eventsauce.doctrine.connection'),
                $tableName,
                new Reference(SnapshotStateSerializer::class),
                new Reference(UuidEncoder::class),
            ]);
        }

        $regularRepositoryDef = $this->container->getDefinition("andreo.eventsauce.aggregate_repository.$aggregateName");
        if ($snapshotConfig['versioned']) {
            $snapshottingRepositoryDef = new Definition(AggregateRootRepositoryWithVersionedSnapshotting::class, [
                $aggregateClass,
                new Reference("andreo.eventsauce.message_repository.$aggregateName"),
                $snapshotRepositoryDef,
                $regularRepositoryDef,
            ]);
        } else {
            $snapshottingRepositoryDef = new Definition(ConstructingAggregateRootRepositoryWithSnapshotting::class, [
                $aggregateClass,
                new Reference("andreo.eventsauce.message_repository.$aggregateName"),
                $snapshotRepositoryDef,
                $regularRepositoryDef,
            ]);
        }

        if ($this->extension->isConfigEnabled($this->container, $everyNEventStoreConfig = $storeStrategyConfig['every_n_event'])) {
            $snapshottingRepositoryDef = new Definition(AggregateRootRepositoryWithSnapshottingAndStoreStrategy::class, [
                $snapshottingRepositoryDef,
                new Definition(EveryNEventCanStoreSnapshotStrategy::class, [$everyNEventStoreConfig['number']]),
            ]);
        } elseif ($this->extension->isConfigEnabled($this->container, $customStoreConfig = $storeStrategyConfig['custom'])) {
            $snapshottingRepositoryDef = new Definition(AggregateRootRepositoryWithSnapshottingAndStoreStrategy::class, [
                $snapshottingRepositoryDef,
                new Reference($customStoreConfig['id']),
            ]);
        }

        $this->container
            ->setDefinition("andreo.eventsauce.aggregate_repository.$aggregateName", $snapshottingRepositoryDef)
            ->setPublic(false)
        ;

        $this->container->setAlias($repositoryAlias, "andreo.eventsauce.aggregate_repository.$aggregateName");
        $this->container->registerAliasForArgument($repositoryAlias, AggregateRootRepositoryWithSnapshotting::class);
    }
}
