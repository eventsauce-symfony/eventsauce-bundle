<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Outbox\MessageConsumer\ForwardingMessageConsumer;
use Andreo\EventSauce\Outbox\Repository\EventSourcedAggregateRootRepositoryForOutbox;
use Andreo\EventSauce\Snapshotting\Conditional\AggregateRootRepositoryWithConditionalSnapshot;
use Andreo\EventSauce\Snapshotting\Conditional\ConditionalSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\Conditional\EveryNEventConditionalSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\Doctrine\DoctrineSnapshotRepository;
use Andreo\EventSauce\Snapshotting\Doctrine\Table\SnapshotTableSchema;
use Andreo\EventSauce\Snapshotting\Serializer\SnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\Versioned\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\Versioned\SnapshotVersionComparator;
use Andreo\EventSauce\Snapshotting\Versioned\SnapshotVersionInflector;
use Andreo\EventSauce\Upcasting\MessageUpcaster\MessageUpcasterChain;
use Andreo\EventSauce\Upcasting\MessageUpcaster\UpcastingMessageObjectSerializer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\DependencyInjection\Utils\ReflectionTool;
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
use EventSauce\MessageOutbox\OutboxRelay;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use EventSauce\UuidEncoding\UuidEncoder;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

final readonly class AggregatesLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        array $config
    ): void {
        $messageStorageConfig = $config['message_storage'];
        $snapshotConfig = $config['snapshot'];
        $upcasterConfig = $config['upcaster'];

        foreach ($config['aggregates'] as $aggregateName => $aggregateConfig) {
            $aggregateConfig['repository_alias'] ??= sprintf('%sRepository', $aggregateName);
            $aggregateClassShortName = ReflectionTool::getLowerStringOfClassShortName($aggregateConfig['class']);

            self::loadMessageDispatchers(
                $container,
                $aggregateName,
                $aggregateConfig,
                $config,
            );

            self::loadMessageSerializerAndUpcaster(
                $extension,
                $container,
                $aggregateConfig,
                $upcasterConfig,
                $aggregateClassShortName
            );

            if ($extension->isConfigEnabled($container, $messageStorageConfig)) {
                self::loadMessageRepository(
                    $extension,
                    $container,
                    $aggregateName,
                    $messageStorageConfig,
                    $aggregateClassShortName
                );
            }

            self::loadEventSourcedAggregateRootRepository(
                $container,
                $aggregateName,
                $aggregateConfig
            );

            if ($extension->isConfigEnabled($container, $aggregateConfig['message_outbox'])) {
                self::loadAggregateRootRepositoryWithMessageOutbox(
                    $extension,
                    $container,
                    $aggregateName,
                    $aggregateConfig,
                    $config['message_outbox']
                );
            }

            if ($extension->isConfigEnabled($container, $aggregateConfig['snapshot'])) {
                self::loadAggregateRootRepositoryWithSnapshotting(
                    $extension,
                    $container,
                    $aggregateName,
                    $aggregateConfig,
                    $snapshotConfig
                );
            }
        }
    }

    private static function loadMessageDispatchers(
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig,
        array $config
    ): void {
        $aggregateDispatchers = $aggregateConfig['dispatchers'];
        if (empty($aggregateDispatchers)) {
            $container->setAlias(
                "andreo.eventsauce.message_dispatcher_chain.$aggregateName",
                'andreo.eventsauce.message_dispatcher_chain'
            );
        } else {
            /** @var string[] $messageDispatcherChain */
            $messageDispatcherChain = array_keys($config['message_dispatcher']);
            $messageDispatcherRefers = [];
            foreach ($messageDispatcherChain as $messageDispatcherAlias) {
                $messageDispatcherRefers[] = new Reference($messageDispatcherAlias);
            }

            $container
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

    private static function loadMessageSerializerAndUpcaster(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        array $aggregateConfig,
        array $upcasterConfig,
        string $aggregateClassShortName
    ): void {
        if ($extension->isConfigEnabled($container, $aggregateConfig['upcaster'])) {
            if (!$extension->isConfigEnabled($container, $upcasterConfig)) {
                throw new LogicException('Upcaster config is disabled.');
            }
            $trigger = $upcasterConfig['trigger'];
            if ('before_unserialize' === $trigger) {
                $container
                    ->register("andreo.eventsauce.upcaster_chain.$aggregateClassShortName", UpcasterChain::class)
                    ->setFactory([UpcasterChainFactory::class, 'create'])
                ;
                $container
                    ->register("andreo.eventsauce.message_serializer.$aggregateClassShortName", UpcastingMessageSerializer::class)
                    ->setArguments([
                        new Reference(MessageSerializer::class),
                        new Reference("andreo.eventsauce.upcaster_chain.$aggregateClassShortName"),
                    ])
                ;
            } elseif ('after_unserialize' === $trigger) {
                $container
                    ->register("andreo.eventsauce.upcaster_chain.$aggregateClassShortName", MessageUpcasterChain::class)
                    ->setFactory([MessageUpcasterChainFactory::class, 'create'])
                    ->addArgument([])
                ;
                $container
                    ->register("andreo.eventsauce.message_serializer.$aggregateClassShortName", UpcastingMessageObjectSerializer::class)
                    ->setArguments([
                        new Reference(MessageSerializer::class),
                        new Reference("andreo.eventsauce.upcaster_chain.$aggregateClassShortName"),
                    ])
                ;
            } else {
                throw new LogicException('Should not be executed.');
            }
        } else {
            $container->setAlias("andreo.eventsauce.message_serializer.$aggregateClassShortName", MessageSerializer::class);
        }
    }

    private static function loadMessageRepository(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        string $aggregateName,
        array $messageStorageConfig,
        string $aggregateClassShortName
    ): void {
        $messageRepositoryConfig = $messageStorageConfig['repository'];
        $doctrineRepositoryEnabled = $extension->isConfigEnabled(
            $container,
            $messageRepositoryDoctrineConfig = $messageRepositoryConfig['doctrine_3']
        );

        if ($doctrineRepositoryEnabled) {
            $jsonEncodeFlags = $messageRepositoryDoctrineConfig['json_encode_flags'];
            $messageTableName = $messageRepositoryDoctrineConfig['table_name'];
            $tableName = sprintf('%s_%s', $aggregateName, $messageTableName);

            $container
                ->register("andreo.eventsauce.message_repository.$aggregateName", DoctrineUuidV4MessageRepository::class)
                ->setArguments([
                    new Reference('andreo.eventsauce.doctrine.connection'),
                    $tableName,
                    new Reference("andreo.eventsauce.message_serializer.$aggregateClassShortName"),
                    array_reduce($jsonEncodeFlags, static fn (int $a, int $b) => $a | $b, 0),
                    new Reference(TableSchema::class),
                    new Reference(UuidEncoder::class),
                ])
                ->setPublic(false)
            ;
        } else {
            $container
                ->register("andreo.eventsauce.message_repository.$aggregateName", InMemoryMessageRepository::class)
                ->setPublic(false);
        }
    }

    private static function loadEventSourcedAggregateRootRepository(
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig
    ): void {
        $aggregateClass = $aggregateConfig['class'];
        $repositoryAlias = $aggregateConfig['repository_alias'];

        $container
            ->register("andreo.eventsauce.aggregate_root_repository.$aggregateName", EventSourcedAggregateRootRepository::class)
            ->setArguments([
                $aggregateClass,
                new Reference("andreo.eventsauce.message_repository.$aggregateName"),
                new Reference("andreo.eventsauce.message_dispatcher_chain.$aggregateName"),
                new Reference('andreo.eventsauce.message_decorator_chain'),
                new Reference(ClassNameInflector::class),
            ])
            ->setPublic(false)
        ;

        $container->setAlias($repositoryAlias, "andreo.eventsauce.aggregate_root_repository.$aggregateName");
        $container->registerAliasForArgument($repositoryAlias, AggregateRootRepository::class);
    }

    private static function loadAggregateRootRepositoryWithMessageOutbox(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig,
        array $outboxConfig
    ): void {
        $outboxRepositoryConfig = $outboxConfig['repository'];
        $aggregateClass = $aggregateConfig['class'];

        if (!$extension->isConfigEnabled($container, $outboxConfig)) {
            throw new LogicException('Message Outbox config is disabled.');
        }

        $doctrineRepositoryEnabled = $extension->isConfigEnabled(
            $container,
            $outboxRepositoryConfig['doctrine']
        );
        if ($doctrineRepositoryEnabled) {
            $outboxRepositoryDoctrineConfig = $outboxRepositoryConfig['doctrine'];
            $tableName = sprintf('%s_%s', $aggregateName, $outboxRepositoryDoctrineConfig['table_name']);
            $container
                ->register("andreo.eventsauce.outbox_repository.$aggregateName", DoctrineOutboxRepository::class)
                ->setArguments([
                    new Reference('andreo.eventsauce.doctrine.connection'),
                    $tableName,
                    new Reference(MessageSerializer::class),
                ])
                ->setPublic(false)
            ;

            $decoratedMessageRepositoryDef = $container->getDefinition("andreo.eventsauce.message_repository.$aggregateName");
            $container
                ->register("andreo.eventsauce.message_repository.$aggregateName", DoctrineTransactionalMessageRepository::class)
                ->setArguments([
                    new Reference('andreo.eventsauce.doctrine.connection'),
                    $decoratedMessageRepositoryDef,
                    new Reference("andreo.eventsauce.outbox_repository.$aggregateName"),
                ])
                ->setPublic(false)
            ;
        } else {
            $container
                ->register("andreo.eventsauce.message_repository.$aggregateName", InMemoryMessageRepository::class)
                ->setPublic(false);
        }

        $decoratedAggregateRootRepositoryDef = $container->getDefinition("andreo.eventsauce.aggregate_root_repository.$aggregateName");
        $container
            ->register("andreo.eventsauce.aggregate_root_repository.$aggregateName", EventSourcedAggregateRootRepositoryForOutbox::class)
            ->setArguments([
                $aggregateClass,
                new Reference("andreo.eventsauce.message_repository.$aggregateName"),
                $decoratedAggregateRootRepositoryDef,
                new Reference('andreo.eventsauce.message_decorator_chain'),
                new Reference(ClassNameInflector::class),
            ])
            ->setPublic(false)
        ;

        $messageConsumerDefinition = new Definition(ForwardingMessageConsumer::class, [
            new Reference("andreo.eventsauce.message_dispatcher_chain.$aggregateName"),
        ]);

        $container->setAlias("andreo.eventsauce.outbox.back_off_strategy.$aggregateName", BackOffStrategy::class);
        $container->setAlias("andreo.eventsauce.outbox.relay_commit_strategy.$aggregateName", RelayCommitStrategy::class);

        $aggregateOutboxConfig = $aggregateConfig['message_outbox'];
        $relayId = $aggregateOutboxConfig['relay_id'] ?? sprintf('%s_aggregate_relay', $aggregateName);

        $container
            ->register("andreo.eventsauce.outbox_relay.$aggregateName", OutboxRelay::class)
            ->setArguments([
                new Reference("andreo.eventsauce.outbox_repository.$aggregateName"),
                $messageConsumerDefinition,
                new Reference("andreo.eventsauce.outbox.back_off_strategy.$aggregateName"),
                new Reference("andreo.eventsauce.outbox.relay_commit_strategy.$aggregateName"),
            ])
            ->addTag('andreo.eventsauce.outbox_relay', [
                'relay_id' => $relayId,
            ])
            ->setPublic(false)
        ;
    }

    private static function loadAggregateRootRepositoryWithSnapshotting(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig,
        array $snapshotConfig
    ): void {
        if (!$extension->isConfigEnabled($container, $snapshotConfig)) {
            throw new LogicException(sprintf('To use snapshot for aggregate "%s", snapshot must be enabled .', $aggregateName));
        }

        $aggregateClass = $aggregateConfig['class'];
        $repositoryConfig = $snapshotConfig['repository'];
        $repositoryAlias = $aggregateConfig['repository_alias'];

        $repositoryEnabled = $extension->isConfigEnabled(
            $container,
            $repositoryConfig['doctrine']
        );

        $doctrineRepositoryEnabled = $repositoryEnabled && $extension->isConfigEnabled(
            $container,
            $repositoryConfig['doctrine']
        );

        if ($doctrineRepositoryEnabled) {
            $snapshotDoctrineRepositoryConfig = $repositoryConfig['doctrine'];
            $tableName = sprintf('%s_%s', $aggregateName, $snapshotDoctrineRepositoryConfig['table_name']);

            $container
                ->register("andreo.eventsauce.snapshot_repository.$aggregateName", DoctrineSnapshotRepository::class)
                ->setArguments([
                    new Reference('andreo.eventsauce.doctrine.connection'),
                    $tableName,
                    new Reference(SnapshotStateSerializer::class),
                    new Reference(UuidEncoder::class),
                    new Reference(SnapshotTableSchema::class),
                    '%andreo.eventsauce.snapshot.doctrine_repository.json_depth%',
                    '%andreo.eventsauce.snapshot.doctrine_repository.json_encode_flags%',
                    '%andreo.eventsauce.snapshot.doctrine_repository.json_decode_flags%',
                ])->setPublic(false)
            ;
        } else {
            $container
                ->register(
                    "andreo.eventsauce.snapshot_repository.$aggregateName",
                    InMemorySnapshotRepository::class
                )->setPublic(false);
        }

        $aggregateSnapshotConfig = $aggregateConfig['snapshot'];
        $decoratedAggregateRootRepositoryDef = $container->getDefinition("andreo.eventsauce.aggregate_root_repository.$aggregateName");
        if (!$extension->isConfigEnabled($container, $snapshotConfig['versioned'])) {
            $aggregateRootRepositoryWithSnapshottingDef = new Definition(ConstructingAggregateRootRepositoryWithSnapshotting::class, [
                $aggregateClass,
                new Reference("andreo.eventsauce.message_repository.$aggregateName"),
                new Reference("andreo.eventsauce.snapshot_repository.$aggregateName"),
                $decoratedAggregateRootRepositoryDef,
            ]);
        } else {
            $aggregateRootRepositoryWithSnapshottingDef = new Definition(AggregateRootRepositoryWithVersionedSnapshotting::class, [
                $aggregateClass,
                new Reference("andreo.eventsauce.message_repository.$aggregateName"),
                new Reference("andreo.eventsauce.snapshot_repository.$aggregateName"),
                $decoratedAggregateRootRepositoryDef,
                new Reference(SnapshotVersionInflector::class),
                new Reference(SnapshotVersionComparator::class),
            ]);
        }

        if ($extension->isConfigEnabled($container, $conditionalConfig = $aggregateSnapshotConfig['conditional'])) {
            if ($extension->isConfigEnabled($container, $everyNEventConfig = $conditionalConfig['every_n_event'])) {
                $container
                    ->register("andreo.eventsauce.snapshot.conditional_strategy.$aggregateName", EveryNEventConditionalSnapshotStrategy::class)
                    ->addArgument($everyNEventConfig['number']);
            } else {
                $container->setAlias("andreo.eventsauce.snapshot.conditional_strategy.$aggregateName", ConditionalSnapshotStrategy::class);
            }

            $aggregateRootRepositoryWithSnapshottingDef = new Definition(AggregateRootRepositoryWithConditionalSnapshot::class, [
                $aggregateRootRepositoryWithSnapshottingDef,
                new Reference("andreo.eventsauce.snapshot.conditional_strategy.$aggregateName"),
            ]);
        }

        $container
            ->setDefinition("andreo.eventsauce.aggregate_root_repository.$aggregateName", $aggregateRootRepositoryWithSnapshottingDef)
            ->setPublic(false)
        ;

        $container->setAlias($repositoryAlias, "andreo.eventsauce.aggregate_root_repository.$aggregateName");
        $container->registerAliasForArgument($repositoryAlias, AggregateRootRepositoryWithSnapshotting::class);
    }
}
