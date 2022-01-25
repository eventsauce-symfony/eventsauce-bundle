<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection;

use Andreo\EventSauce\Doctrine\Migration\GenerateAggregateMigrationCommand;
use Andreo\EventSauce\Doctrine\Migration\TableNameSuffix;
use Andreo\EventSauce\Messenger\MessengerEventWithHeadersDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageEventDispatcher;
use Andreo\EventSauce\Outbox\AggregateRootRepositoryWithoutDispatchMessage;
use Andreo\EventSauce\Outbox\ForwardingMessageConsumer;
use Andreo\EventSauce\Outbox\RelayOutboxMessagesCommand;
use Andreo\EventSauce\Serialization\SymfonyPayloadSerializer;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithSnapshottingAndStoreStrategy;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\CanStoreSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\ConstructingSnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\DoctrineDbalSnapshotRepository;
use Andreo\EventSauce\Snapshotting\EveryNEventCanStoreSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauce\Upcasting\MessageUpcasterChain;
use Andreo\EventSauce\Upcasting\UpcasterChainWithEventGuessing;
use Andreo\EventSauce\Upcasting\UpcastingMessageObjectSerializer;
use Andreo\EventSauceBundle\Attribute\AsMessageConsumer;
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\DelegatingMessageDecoratorChain;
use Andreo\EventSauceBundle\DelegatingMessageDispatcherChain;
use Andreo\EventSauceBundle\DelegatingSynchronousMessageDispatcher;
use Andreo\EventSauceBundle\NothingMessageDecorator;
use DateTimeZone;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\BackOff\ExponentialBackOffStrategy;
use EventSauce\BackOff\FibonacciBackOffStrategy;
use EventSauce\BackOff\ImmediatelyFailingBackOffStrategy;
use EventSauce\BackOff\LinearBackOffStrategy;
use EventSauce\BackOff\NoWaitingBackOffStrategy;
use EventSauce\Clock\Clock;
use EventSauce\Clock\SystemClock;
use EventSauce\EventSourcing\AggregateRootRepository;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\ConstructingAggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\InMemorySnapshotRepository;
use EventSauce\EventSourcing\Upcasting\UpcasterChain;
use EventSauce\EventSourcing\Upcasting\UpcastingMessageSerializer;
use EventSauce\MessageOutbox\DeleteMessageOnCommit;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineOutboxRepository;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineTransactionalMessageRepository;
use EventSauce\MessageOutbox\InMemoryOutboxRepository;
use EventSauce\MessageOutbox\OutboxRelay;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use EventSauce\UuidEncoding\BinaryUuidEncoder;
use EventSauce\UuidEncoding\UuidEncoder;
use LogicException;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class AndreoEventSauceExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->getConfiguration([], $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('eventsauce.yaml');

        $this->loadTime($container, $config);
        $this->loadMessageRepository($container, $config);
        $this->loadMessageDispatcher($container, $config);
        $this->loadMessageOutbox($container, $loader, $config);
        $this->loadSnapshot($container, $loader, $config);
        $this->loadUpcast($container, $config);
        $this->loadPayloadSerializer($container, $loader, $config);
        $this->loadUuidEncoder($container, $config);
        $this->loadClassNameInflector($container, $config);
        $this->loadAggregatesConfiguration($config, $container);
        $this->loadGenerateMigration($container, $loader, $config);
    }

    private function loadTime(ContainerBuilder $container, array $config): void
    {
        $timeConfig = $config['time'];
        $recordingTimezoneConfig = $timeConfig['recording_timezone'];
        $container
            ->register('andreo.event_sauce.recording_timezone', DateTimeZone::class)
            ->addArgument($recordingTimezoneConfig)
            ->setPublic(false)
        ;

        $clockServiceId = $timeConfig['clock'];
        if (!in_array($clockServiceId, [null, Clock::class, SystemClock::class], true)) {
            $container->setAlias(Clock::class, $clockServiceId);
        }
    }

    private function loadMessageRepository(ContainerBuilder $container, array $config): void
    {
        $messageConfig = $config['message'];
        $messageRepositoryConfig = $messageConfig['repository'];
        $messageRepositoryDoctrineConfig = $messageRepositoryConfig['doctrine'];
        $connectionServiceId = $messageRepositoryDoctrineConfig['connection'];

        $container->setAlias('andreo.event_sauce.doctrine.connection', $connectionServiceId);

        $tableSchemaServiceId = $messageRepositoryDoctrineConfig['table_schema'];

        if (!in_array($tableSchemaServiceId, [null, TableSchema::class, DefaultTableSchema::class], true)) {
            $container->setAlias(TableSchema::class, $tableSchemaServiceId);
        }

        $messageSerializerServiceId = $messageConfig['serializer'];
        if (!in_array($messageSerializerServiceId, [null, MessageSerializer::class, ConstructingMessageSerializer::class], true)) {
            $container->setAlias(MessageSerializer::class, $messageSerializerServiceId);
        }

        $messageDecoratorEnabled = $messageConfig['decorator'];
        if ($messageDecoratorEnabled) {
            $container->registerAttributeForAutoconfiguration(
                AsMessageDecorator::class,
                static function (ChildDefinition $definition, AsMessageDecorator $attribute): void {
                    $aggregateName = $attribute->aggregate;
                    $definition->addTag("andreo.event_sauce.message_decorator.$aggregateName", ['priority' => -$attribute->order]);
                }
            );
        }
    }

    private function loadMessageDispatcher(ContainerBuilder $container, array $config): void
    {
        $messageConfig = $config['message'];
        $messageDispatcherConfig = $messageConfig['dispatcher'];
        $messengerConfig = $messageDispatcherConfig['messenger'];
        $messengerEnabled = $this->isConfigEnabled($container, $messengerConfig);
        $mode = $messengerConfig['mode'];
        $chainConfig = $messageDispatcherConfig['chain'];

        if (!$messengerEnabled) {
            $container->registerAttributeForAutoconfiguration(
                AsMessageConsumer::class,
                static function (ChildDefinition $definition, AsMessageConsumer $attribute): void {
                    $dispatcherName = $attribute->dispatcher;
                    $definition->addTag("andreo.event_sauce.message_consumer.$dispatcherName");
                }
            );
        }
        foreach ($chainConfig as $dispatcherName => $dispatcherServiceId) {
            if ($messengerEnabled) {
                if (!class_exists(MessengerMessageDispatcher::class)) {
                    throw new LogicException('Messenger message dispatcher is not installed. Try running "composer require andreo/eventsauce-snapshotting".');
                }

                if ('event' === $mode) {
                    $container
                        ->register("andreo.event_sauce.message_dispatcher.$dispatcherName", MessengerMessageEventDispatcher::class)
                        ->addArgument(new Reference($dispatcherServiceId))
                        ->setPublic(false)
                    ;
                } elseif ('message' === $mode) {
                    $container
                        ->register("andreo.event_sauce.message_dispatcher.$dispatcherName", MessengerMessageDispatcher::class)
                        ->addArgument(new Reference($dispatcherServiceId))
                        ->setPublic(false)
                        ->setPublic(false)
                    ;
                } else {
                    $container
                        ->register("andreo.event_sauce.message_dispatcher.$dispatcherName", MessengerEventWithHeadersDispatcher::class)
                        ->addArgument(new Reference($dispatcherServiceId))
                        ->setPublic(false)
                        ->addTag('andreo.event_sauce.event_with_headers_dispatcher', [
                            'bus' => $dispatcherServiceId,
                        ])
                    ;
                }
            } elseif (in_array($dispatcherServiceId, [null, 'default'], true)) {
                $container
                    ->register("andreo.event_sauce.message_dispatcher.$dispatcherName", DelegatingSynchronousMessageDispatcher::class)
                    ->addArgument(new TaggedIteratorArgument("andreo.event_sauce.message_consumer.$dispatcherName"))
                    ->setPublic(false)
                ;
                $container->setAlias($dispatcherName, "andreo.event_sauce.message_dispatcher.$dispatcherName");
                $container->registerAliasForArgument($dispatcherName, MessageDispatcher::class);
            } else {
                $container->setAlias("andreo.event_sauce.message_dispatcher.$dispatcherName", $dispatcherServiceId);
            }
        }
    }

    private function loadMessageOutbox(ContainerBuilder $container, YamlFileLoader $loader, array $config): void
    {
        $messageConfig = $config['message'];
        $messageOutboxConfig = $messageConfig['outbox'];
        if (!$this->isConfigEnabled($container, $messageOutboxConfig)) {
            return;
        }
        if (!class_exists(AggregateRootRepositoryWithoutDispatchMessage::class)) {
            throw new LogicException('Message outbox is not available. Try running "composer require andreo/eventsauce-outbox".');
        }

        $loader->load('outbox.yaml');

        $initialDelayMsParam = '%andreo.event_sauce.outbox.back_off.initial_delay_ms%';
        $maxTriesParam = '%andreo.event_sauce.outbox.back_off.max_tries%';

        $backOffConfig = $messageOutboxConfig['back_off'];
        if ($this->isConfigEnabled($container, $exponentialConfig = $backOffConfig['exponential'])) {
            $initialDelayMs = $exponentialConfig['initial_delay_ms'];
            $maxTries = $exponentialConfig['max_tries'];
            if (null !== $initialDelayMs || null !== $maxTries) {
                $container
                    ->getDefinition(ExponentialBackOffStrategy::class)
                    ->replaceArgument(0, $initialDelayMs ?? $initialDelayMsParam)
                    ->replaceArgument(1, $maxTries ?? $maxTriesParam)
                ;
            }
        } elseif ($this->isConfigEnabled($container, $fibonacciConfig = $backOffConfig['fibonacci'])) {
            $initialDelayMs = $fibonacciConfig['initial_delay_ms'];
            $maxTries = $fibonacciConfig['max_tries'];
            if (null !== $initialDelayMs || null !== $maxTries) {
                $container
                    ->getDefinition(FibonacciBackOffStrategy::class)
                    ->replaceArgument(1, $maxTries ?? $maxTriesParam)
                ;
            }
            $container->setAlias(BackOffStrategy::class, FibonacciBackOffStrategy::class);
        } elseif ($this->isConfigEnabled($container, $linearBackConfig = $backOffConfig['linear_back'])) {
            $initialDelayMs = $linearBackConfig['initial_delay_ms'];
            $maxTries = $linearBackConfig['max_tries'];
            if (null !== $initialDelayMs || null !== $maxTries) {
                $container
                    ->getDefinition(LinearBackOffStrategy::class)
                    ->replaceArgument(0, $initialDelayMs ?? $initialDelayMsParam)
                    ->replaceArgument(1, $maxTries ?? $maxTriesParam)
                ;
            }
            $container->setAlias(BackOffStrategy::class, LinearBackOffStrategy::class);
        } elseif ($this->isConfigEnabled($container, $noWaitingConfig = $backOffConfig['no_waiting'])) {
            $maxTries = $noWaitingConfig['max_tries'];
            if (null !== $maxTries) {
                $container
                    ->getDefinition(NoWaitingBackOffStrategy::class)
                    ->replaceArgument(0, $maxTries)
                ;
            }
            $container->setAlias(BackOffStrategy::class, NoWaitingBackOffStrategy::class);
        } elseif ($this->isConfigEnabled($container, $backOffConfig['immediately_failing'])) {
            $container->setAlias(BackOffStrategy::class, ImmediatelyFailingBackOffStrategy::class);
        } elseif ($this->isConfigEnabled($container, $customConfig = $backOffConfig['custom'])) {
            $container->setAlias(BackOffStrategy::class, $customConfig['id']);
        }

        $relayCommitConfig = $messageOutboxConfig['relay_commit'];
        if ($this->isConfigEnabled($container, $relayCommitConfig['delete'])) {
            $container->setAlias(RelayCommitStrategy::class, DeleteMessageOnCommit::class);
        }
    }

    private function loadSnapshot(ContainerBuilder $container, YamlFileLoader $loader, array $config): void
    {
        $needLoad = false;
        $snapshotConfig = $config['snapshot'];
        $snapshotRepositoryConfig = $snapshotConfig['repository'];
        if ($this->isConfigEnabled($container, $snapshotRepositoryConfig['doctrine'])) {
            if (!class_exists(DoctrineDbalSnapshotRepository::class)) {
                throw new LogicException('Doctrine snapshot repository is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        $storeStrategyConfig = $snapshotConfig['store_strategy'];
        if ($this->isConfigEnabled($container, $storeStrategyConfig['every_n_event'])) {
            if (!interface_exists(CanStoreSnapshotStrategy::class)) {
                throw new LogicException('Store snapshot strategy is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        $serializer = $snapshotConfig['serializer'];
        if (null !== $serializer) {
            if (!interface_exists(SnapshotStateSerializer::class)) {
                throw new LogicException('Snapshot state serializer is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        $versioned = $snapshotConfig['versioned'];
        if ($versioned) {
            if (!class_exists(AggregateRootRepositoryWithVersionedSnapshotting::class)) {
                throw new LogicException('Versioned snapshotting is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        if ($needLoad) {
            $loader->load('snapshot.yaml');
        }

        $snapshotSerializerServiceId = $snapshotConfig['serializer'];
        if (!in_array($snapshotSerializerServiceId, [null, SnapshotStateSerializer::class, ConstructingSnapshotStateSerializer::class], true)) {
            $container->setAlias(SnapshotStateSerializer::class, $snapshotSerializerServiceId);
        }
    }

    private function loadUpcast(ContainerBuilder $container, array $config): void
    {
        $upcastConfig = $config['upcast'];
        if (!$this->isConfigEnabled($container, $upcastConfig)) {
            return;
        }

        $container->registerAttributeForAutoconfiguration(
            AsUpcaster::class,
            static function (ChildDefinition $definition, AsUpcaster $attribute): void {
                $aggregateName = $attribute->aggregate;
                $definition->addTag("andreo.event_sauce.upcaster.$aggregateName", ['priority' => -$attribute->version]);
            }
        );
    }

    private function loadAggregatesConfiguration(array $config, ContainerBuilder $container): void
    {
        $messageConfig = $config['message'];
        $snapshotConfig = $config['snapshot'];
        $upcastConfig = $config['upcast'];

        foreach ($config['aggregates'] as $aggregateName => $aggregateConfig) {
            $aggregateMessageConfig = $aggregateConfig['message'];
            $aggregateOutboxEnabled = $aggregateMessageConfig['outbox'];
            $aggregateSnapshotEnabled = $aggregateConfig['snapshot'];

            $repositoryAlias = $aggregateConfig['repository_alias'];
            if (null === $repositoryAlias) {
                $aggregateConfig['repository_alias'] = sprintf('%s%s', $aggregateName, 'Repository');
            }

            $this->loadAggregateDispatchersConfiguration(
                $aggregateName,
                $messageConfig,
                $aggregateConfig,
                $container
            );

            $this->loadAggregateMessageRepositoryConfiguration(
                $aggregateName,
                $aggregateConfig,
                $messageConfig,
                $upcastConfig,
                $container
            );

            if ($aggregateOutboxEnabled) {
                $this->loadAggregateOutboxConfiguration($aggregateName, $messageConfig, $container);
            }

            $messageRepositoryRef = $aggregateOutboxEnabled ?
                new Reference("andreo.event_sauce.transactional_message_repository.$aggregateName") :
                new Reference("andreo.event_sauce.message_repository.$aggregateName");

            $this->loadAggregateRepositoryConfiguration(
                $aggregateName,
                $aggregateConfig,
                $messageConfig,
                $messageRepositoryRef,
                $container
            );

            if ($aggregateSnapshotEnabled) {
                $this->loadAggregateSnapshotConfiguration(
                    $aggregateName,
                    $aggregateConfig,
                    $snapshotConfig,
                    $container,
                    $messageRepositoryRef
                );
            }
        }
    }

    private function loadAggregateDispatchersConfiguration(
        string $aggregateName,
        array $messageConfig,
        array $aggregateConfig,
        ContainerBuilder $container
    ): void {
        $messageDispatcherConfig = $messageConfig['dispatcher'];
        $dispatcherChainNames = array_keys($messageDispatcherConfig['chain']);
        $aggregateMessageConfig = $aggregateConfig['message'];
        $aggregateDispatchers = $aggregateMessageConfig['dispatchers'];

        $aggregateDispatchers = empty($aggregateDispatchers) ? $dispatcherChainNames : $aggregateDispatchers;
        $messageDispatcherRefers = [];
        foreach ($aggregateDispatchers as $aggregateDispatcher) {
            if (!in_array($aggregateDispatcher, $dispatcherChainNames, true)) {
                throw new RuntimeException(sprintf('Dispatcher with name "%s" is not configured. Configure it in the message section.', $aggregateDispatcher));
            }
            $messageDispatcherRefers[] = new Reference("andreo.event_sauce.message_dispatcher.$aggregateDispatcher");
        }

        $container
            ->register(
            "andreo.event_sauce.message_dispatcher_chain.$aggregateName",
            DelegatingMessageDispatcherChain::class
        )
            ->addArgument(new IteratorArgument($messageDispatcherRefers))
            ->setPublic(false)
        ;
    }

    private function loadAggregateMessageRepositoryConfiguration(
        string $aggregateName,
        array $aggregateConfig,
        array $messageConfig,
        array $upcastConfig,
        ContainerBuilder $container
    ): void {
        $upcastEnabled = $aggregateConfig['upcast'];
        $messageRepositoryConfig = $messageConfig['repository'];
        $messageRepositoryDoctrineConfig = $messageRepositoryConfig['doctrine'];
        $jsonEncodeOptions = $messageRepositoryConfig['json_encode_options'];
        $messageTableName = $messageRepositoryDoctrineConfig['table_name'];

        if ($upcastEnabled) {
            if (!$this->isConfigEnabled($container, $upcastConfig)) {
                throw new LogicException('Upcast config is disabled. If you want to use it, enable and configure it .');
            }

            $context = $upcastConfig['context'];
            if ('payload' === $context) {
                if (!class_exists(UpcasterChainWithEventGuessing::class)) {
                    $upcasterChainDef = new Definition(UpcasterChain::class, [
                        new TaggedIteratorArgument("andreo.event_sauce.upcaster.$aggregateName"),
                        new Reference(ClassNameInflector::class),
                    ]);
                } else {
                    $upcasterChainDef = new Definition(UpcasterChainWithEventGuessing::class, [
                        new TaggedIteratorArgument("andreo.event_sauce.upcaster.$aggregateName"),
                        new Reference(ClassNameInflector::class),
                    ]);
                }

                $messageSerializerArgument = new Definition(UpcastingMessageSerializer::class, [
                    new Reference(MessageSerializer::class),
                    $upcasterChainDef,
                ]);
            } else {
                $upcasterChainDef = new Definition(MessageUpcasterChain::class, [
                    new TaggedIteratorArgument("andreo.event_sauce.upcaster.$aggregateName"),
                ]);
                $messageSerializerArgument = new Definition(UpcastingMessageObjectSerializer::class, [
                    new Reference(MessageSerializer::class),
                    $upcasterChainDef,
                ]);
            }
        } else {
            $messageSerializerArgument = new Reference(MessageSerializer::class);
        }

        $tableName = sprintf('%s_%s', $aggregateName, $messageTableName);
        $container
            ->register("andreo.event_sauce.message_repository.$aggregateName", DoctrineUuidV4MessageRepository::class)
            ->setArguments([
                new Reference('andreo.event_sauce.doctrine.connection'),
                $tableName,
                $messageSerializerArgument,
                array_reduce($jsonEncodeOptions, static fn (int $a, int $b) => $a | $b, 0),
                new Reference(TableSchema::class),
                new Reference(UuidEncoder::class),
            ])
            ->setPublic(false)
        ;
    }

    private function loadAggregateOutboxConfiguration(
        string $aggregateName,
        array $messageConfig,
        ContainerBuilder $container
    ): void {
        $outboxConfig = $messageConfig['outbox'];
        $outboxRepositoryConfig = $outboxConfig['repository'];
        if (!$this->isConfigEnabled($container, $outboxConfig)) {
            throw new LogicException('Message outbox config is disabled. If you want to use it, enable and configure it .');
        }

        if ($this->isConfigEnabled($container, $outboxRepositoryConfig['memory'])) {
            $container
                ->register("andreo.event_sauce.outbox_repository.$aggregateName", InMemoryOutboxRepository::class)
                ->setPublic(false)
            ;
        } elseif ($this->isConfigEnabled($container, $outboxRepositoryDoctrineConfig = $outboxRepositoryConfig['doctrine'])) {
            $tableName = sprintf('%s_%s', $aggregateName, $outboxRepositoryDoctrineConfig['table_name']);
            $container
                ->register("andreo.event_sauce.outbox_repository.$aggregateName", DoctrineOutboxRepository::class)
                ->setArguments([
                    new Reference('andreo.event_sauce.doctrine.connection'),
                    $tableName,
                    new Reference(MessageSerializer::class),
                ])
                ->setPublic(false)
            ;
        } else {
            return;
        }

        $container
            ->register("andreo.event_sauce.transactional_message_repository.$aggregateName", DoctrineTransactionalMessageRepository::class)
            ->setArguments([
                new Reference('andreo.event_sauce.doctrine.connection'),
                new Reference("andreo.event_sauce.message_repository.$aggregateName"),
                new Reference("andreo.event_sauce.outbox_repository.$aggregateName"),
            ])
            ->setPublic(false)
        ;

        $messageConsumerDefinition = new Definition(ForwardingMessageConsumer::class, [
            new Reference("andreo.event_sauce.message_dispatcher_chain.$aggregateName"),
        ]);

        $container
            ->register("andreo.event_sauce.outbox_relay.$aggregateName", OutboxRelay::class)
            ->setArguments([
                new Reference("andreo.event_sauce.outbox_repository.$aggregateName"),
                $messageConsumerDefinition,
                new Reference(BackOffStrategy::class),
                new Reference(RelayCommitStrategy::class),
            ])
            ->setPublic(false)
        ;

        $container
            ->register("andreo.event_sauce.relay.outbox_messages_$aggregateName", RelayOutboxMessagesCommand::class)
            ->addArgument(new Reference("andreo.event_sauce.outbox_relay.$aggregateName"))
            ->addTag('console.command', [
                'command' => "andreo:event-sauce:relay-outbox-messages:$aggregateName",
            ])
        ;
    }

    private function loadAggregateRepositoryConfiguration(
        string $aggregateName,
        array $aggregateConfig,
        array $messageConfig,
        Reference $messageRepositoryRef,
        ContainerBuilder $container
    ): void {
        $aggregateClass = $aggregateConfig['class'];
        $aggregateMessageConfig = $aggregateConfig['message'];
        $aggregateOutboxEnabled = $aggregateMessageConfig['outbox'];
        $aggregateMessageDecoratorEnabled = $aggregateMessageConfig['decorator'];
        $repositoryAlias = $aggregateConfig['repository_alias'];

        if ($aggregateMessageDecoratorEnabled) {
            if (!$messageConfig['decorator']) {
                throw new LogicException('Message decorator config is disabled. If you want to use it, enable it.');
            }

            $messageDecoratorArgument = new Definition(DelegatingMessageDecoratorChain::class, [
                new TaggedIteratorArgument("andreo.event_sauce.message_decorator.$aggregateName"),
            ]);
            $container
                ->findDefinition(MessageDecorator::class)
                ->addTag("andreo.event_sauce.message_decorator.$aggregateName", ['priority' => 0]);
        } else {
            $messageDecoratorArgument = new Reference(NothingMessageDecorator::class);
        }

        if (!$aggregateOutboxEnabled) {
            $aggregateRepositoryDef = new Definition(EventSourcedAggregateRootRepository::class, [
                $aggregateClass,
                $messageRepositoryRef,
                new Reference("andreo.event_sauce.message_dispatcher_chain.$aggregateName"),
                $messageDecoratorArgument,
                new Reference(ClassNameInflector::class),
            ]);
        } else {
            $aggregateRepositoryDef = new Definition(AggregateRootRepositoryWithoutDispatchMessage::class, [
                $aggregateClass,
                $messageRepositoryRef,
                $messageDecoratorArgument,
                new Reference(ClassNameInflector::class),
            ]);
        }

        $container
            ->setDefinition("andreo.event_sauce.aggregate_repository.$aggregateName", $aggregateRepositoryDef)
            ->setPublic(false)
        ;

        $container->setAlias($repositoryAlias, "andreo.event_sauce.aggregate_repository.$aggregateName");
        $container->registerAliasForArgument($repositoryAlias, AggregateRootRepository::class);
    }

    private function loadAggregateSnapshotConfiguration(
        string $aggregateName,
        array $aggregateConfig,
        array $snapshotConfig,
        ContainerBuilder $container,
        Reference $messageRepositoryRef
    ): void {
        if (!$this->isConfigEnabled($container, $snapshotConfig)) {
            throw new LogicException(sprintf('To use snapshot for aggregate "%s", you must enable snapshot in main section.', $aggregateName));
        }

        $aggregateClass = $aggregateConfig['class'];
        $snapshotRepositoryConfig = $snapshotConfig['repository'];
        $versioningEnabled = $snapshotConfig['versioned'];
        $repositoryAlias = $aggregateConfig['repository_alias'];
        $storeStrategyConfig = $snapshotConfig['store_strategy'];

        if (true === $snapshotRepositoryConfig['memory']) {
            $snapshotRepositoryDef = new Definition(InMemorySnapshotRepository::class);
        } else {
            $snapshotRepositoryDoctrineConfig = $snapshotRepositoryConfig['doctrine'];
            $tableName = sprintf('%s_%s', $aggregateName, $snapshotRepositoryDoctrineConfig['table_name']);

            $snapshotRepositoryDef = new Definition(DoctrineDbalSnapshotRepository::class, [
                new Reference('andreo.event_sauce.doctrine.connection'),
                $tableName,
                new Reference(SnapshotStateSerializer::class),
                new Reference(UuidEncoder::class),
            ]);
        }

        if ($versioningEnabled) {
            $snapshottingRepositoryDef = new Definition(AggregateRootRepositoryWithVersionedSnapshotting::class, [
                $aggregateClass,
                $messageRepositoryRef,
                $snapshotRepositoryDef,
                new Reference("andreo.event_sauce.aggregate_repository.$aggregateName"),
            ]);
        } else {
            $snapshottingRepositoryDef = new Definition(ConstructingAggregateRootRepositoryWithSnapshotting::class, [
                $aggregateClass,
                $messageRepositoryRef,
                $snapshotRepositoryDef,
                new Reference("andreo.event_sauce.aggregate_repository.$aggregateName"),
            ]);
        }

        if ($this->isConfigEnabled($container, $everyNEventStoreConfig = $storeStrategyConfig['every_n_event'])) {
            $snapshottingRepositoryDef = new Definition(AggregateRootRepositoryWithSnapshottingAndStoreStrategy::class, [
                $snapshottingRepositoryDef,
                new Definition(EveryNEventCanStoreSnapshotStrategy::class, [$everyNEventStoreConfig['number']]),
            ]);
        } elseif ($this->isConfigEnabled($container, $customStoreConfig = $storeStrategyConfig['custom'])) {
            $snapshottingRepositoryDef = new Definition(AggregateRootRepositoryWithSnapshottingAndStoreStrategy::class, [
                $snapshottingRepositoryDef,
                new Reference($customStoreConfig['id']),
            ]);
        }

        $container
            ->setDefinition("andreo.event_sauce.aggregate_snapshotting_repository.$aggregateName", $snapshottingRepositoryDef)
            ->setPublic(false)
        ;

        $container->setAlias($repositoryAlias, "andreo.event_sauce.aggregate_snapshotting_repository.$aggregateName");
        $container->registerAliasForArgument($repositoryAlias, AggregateRootRepositoryWithSnapshotting::class);
    }

    private function loadPayloadSerializer(ContainerBuilder $container, YamlFileLoader $loader, array $config): void
    {
        $payloadSerializer = $config['payload_serializer'];
        if (SymfonyPayloadSerializer::class === $payloadSerializer) {
            if (!class_exists(SymfonyPayloadSerializer::class)) {
                throw new LogicException('Symfony payload serializer is not available. Try running "composer require andreo/eventsauce-symfony-serializer".');
            }

            $loader->load('serialization.yaml');
        }

        $payloadSerializerServiceId = $config['payload_serializer'];
        if (!in_array($payloadSerializerServiceId, [null, PayloadSerializer::class, ConstructingPayloadSerializer::class], true)) {
            $container->setAlias(PayloadSerializer::class, $payloadSerializerServiceId);
        }
    }

    private function loadUuidEncoder(ContainerBuilder $container, array $config): void
    {
        $encoderServiceId = $config['uuid_encoder'];
        if (!in_array($encoderServiceId, [null, UuidEncoder::class, BinaryUuidEncoder::class], true)) {
            $container->setAlias(UuidEncoder::class, $encoderServiceId);
        }
    }

    private function loadClassNameInflector(ContainerBuilder $container, array $config): void
    {
        $aggregateClassNameInflectorServiceId = $config['class_name_inflector'];
        if (!in_array($aggregateClassNameInflectorServiceId, [null, ClassNameInflector::class, DotSeparatedSnakeCaseInflector::class], true)) {
            $container->setAlias(ClassNameInflector::class, $aggregateClassNameInflectorServiceId);
        }
    }

    private function loadGenerateMigration(ContainerBuilder $container, YamlFileLoader $loader, array $config): void
    {
        if (!class_exists(GenerateAggregateMigrationCommand::class)) {
            return;
        }

        $messageDoctrineConfig = $config['message']['repository']['doctrine'];
        $eventTableName = $messageDoctrineConfig['table_name'];

        $snapshotDoctrineConfig = $config['snapshot']['repository']['doctrine'];
        $snapshotTableName = $snapshotDoctrineConfig['table_name'];

        $outboxDoctrineConfig = $config['message']['outbox']['repository']['doctrine'];
        $outboxTableName = $outboxDoctrineConfig['table_name'];

        $container
            ->register(TableNameSuffix::class, TableNameSuffix::class)
            ->setArguments([
                $eventTableName,
                $snapshotTableName,
                $outboxTableName,
            ])
            ->setPublic(false)
        ;

        $loader->load('migration.yaml');
    }
}
