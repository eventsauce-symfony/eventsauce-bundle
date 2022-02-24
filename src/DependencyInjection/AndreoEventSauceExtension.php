<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection;

use Andreo\EventSauce\Doctrine\Migration\GenerateAggregateMigrationCommand;
use Andreo\EventSauce\Doctrine\Migration\TableNameSuffix;
use Andreo\EventSauce\Messenger\MessengerEventAndHeadersDispatcher;
use Andreo\EventSauce\Messenger\MessengerEventDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageDispatcher;
use Andreo\EventSauce\Outbox\EventSourcedAggregateRootRepositoryForOutbox;
use Andreo\EventSauce\Outbox\ForwardingMessageConsumer;
use Andreo\EventSauce\Outbox\OutboxProcessMessagesCommand;
use Andreo\EventSauce\Serialization\SymfonyPayloadSerializer;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithSnapshottingAndStoreStrategy;
use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\CanStoreSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\ConstructingSnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\DoctrineSnapshotRepository;
use Andreo\EventSauce\Snapshotting\EveryNEventCanStoreSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauce\Upcasting\MessageUpcaster;
use Andreo\EventSauce\Upcasting\MessageUpcasterChain;
use Andreo\EventSauce\Upcasting\UpcasterChainWithEventGuessing;
use Andreo\EventSauce\Upcasting\UpcastingMessageObjectSerializer;
use Andreo\EventSauceBundle\Attribute\AsMessageConsumer;
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\Attribute\MessageContext;
use Andreo\EventSauceBundle\Factory\MessageDecoratorChainFactory;
use Andreo\EventSauceBundle\Factory\MessageDispatcherChainFactory;
use Andreo\EventSauceBundle\Factory\MessageUpcasterChainFactory;
use Andreo\EventSauceBundle\Factory\SynchronousMessageDispatcherFactory;
use Andreo\EventSauceBundle\Factory\UpcasterChainFactory;
use DateTimeZone;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
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
use EventSauce\EventSourcing\EventDispatcher;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageDispatcherChain;
use EventSauce\EventSourcing\MessageDispatchingEventDispatcher;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\ConstructingAggregateRootRepositoryWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\InMemorySnapshotRepository;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
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
        $this->loadMessageDecorator($container, $config);
        $this->loadDispatchers($container, $config);
        $this->loadOutbox($container, $loader, $config);
        $this->loadSnapshot($container, $loader, $config);
        $this->loadUpcast($container, $config);
        $this->loadPayloadSerializer($container, $loader, $config);
        $this->loadUuidEncoder($container, $config);
        $this->loadClassNameInflector($container, $config);
        $this->loadGenerateMigration($container, $loader, $config);
        $this->loadAggregates($config, $container);
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
    }

    private function loadMessageDecorator(ContainerBuilder $container, array $config): void
    {
        $messageConfig = $config['message'];
        $messageDispatcherConfig = $messageConfig['dispatcher'];
        $eventDispatcherEnabled = $messageDispatcherConfig['event_dispatcher'];

        if ($this->isConfigEnabled($container, $messageConfig['decorator'])) {
            $container->registerAttributeForAutoconfiguration(
                AsMessageDecorator::class,
                static function (ChildDefinition $definition, AsMessageDecorator $attribute) use ($eventDispatcherEnabled): void {
                    $context = $attribute->context;
                    if (in_array($context, [MessageContext::ALL, MessageContext::AGGREGATE], true)) {
                        $definition->addTag('andreo.event_sauce.aggregate_message_decorator', ['priority' => -$attribute->order]);
                    }
                    if ($eventDispatcherEnabled && in_array($context, [MessageContext::ALL, MessageContext::EVENT_DISPATCHER], true)) {
                        $definition->addTag('andreo.event_sauce.event_dispatcher_message_decorator', ['priority' => -$attribute->order]);
                    }
                }
            );

            $container
                ->findDefinition(MessageDecorator::class)
                ->addTag('andreo.event_sauce.aggregate_message_decorator', ['priority' => 0]);

            $container
                ->register('andreo.event_sauce.aggregate_message_decorator_chain', MessageDecoratorChain::class)
                ->addArgument(new TaggedIteratorArgument('andreo.event_sauce.aggregate_message_decorator'))
                ->setFactory([MessageDecoratorChainFactory::class, 'create'])
                ->setPublic(false)
            ;

            if ($eventDispatcherEnabled) {
                $container
                    ->register('andreo.event_sauce.event_dispatcher_message_decorator_chain', MessageDecoratorChain::class)
                    ->addArgument(new TaggedIteratorArgument('andreo.event_sauce.event_dispatcher_message_decorator'))
                    ->setFactory([MessageDecoratorChainFactory::class, 'create'])
                    ->setPublic(false)
                ;
            }
        } else {
            $container
                ->register('andreo.event_sauce.aggregate_message_decorator_chain', MessageDecoratorChain::class)
                ->addArgument([])
                ->setFactory([MessageDecoratorChainFactory::class, 'create'])
                ->setPublic(false)
            ;
        }
    }

    private function loadDispatchers(ContainerBuilder $container, array $config): void
    {
        $messageConfig = $config['message'];
        $messageDispatcherConfig = $messageConfig['dispatcher'];
        $eventDispatcherEnabled = $this->isConfigEnabled($container, $messageDispatcherConfig['event_dispatcher']);
        $messengerConfig = $messageDispatcherConfig['messenger'];
        $messengerEnabled = $this->isConfigEnabled($container, $messengerConfig);
        $mode = $messengerConfig['mode'];
        $chainConfig = $messageDispatcherConfig['chain'];

        if ($messengerEnabled) {
            if (!class_exists(MessengerMessageDispatcher::class)) {
                throw new LogicException('Messenger message dispatcher is not installed. Try running "composer require andreo/eventsauce-messenger".');
            }
            foreach ($chainConfig as $dispatcherAlias => $busAlias) {
                if ('event' === $mode) {
                    $container
                        ->register("andreo.event_sauce.message_dispatcher.$dispatcherAlias", MessengerEventDispatcher::class)
                        ->addArgument(new Reference($busAlias))
                        ->setPublic(false);
                } elseif ('event_and_headers' === $mode) {
                    $container
                        ->register("andreo.event_sauce.message_dispatcher.$dispatcherAlias", MessengerEventAndHeadersDispatcher::class)
                        ->addArgument(new Reference($busAlias))
                        ->setPublic(false)
                        ->addTag('andreo.event_sauce.event_and_headers_dispatcher', [
                            'bus' => $busAlias,
                        ]);
                } else {
                    $container
                        ->register("andreo.event_sauce.message_dispatcher.$dispatcherAlias", MessengerMessageDispatcher::class)
                        ->addArgument(new Reference($busAlias))
                        ->setPublic(false)
                    ;
                }
                if ($eventDispatcherEnabled) {
                    $this->registerEventDispatcher($container, $dispatcherAlias);
                } else {
                    $container->setAlias($dispatcherAlias, "andreo.event_sauce.message_dispatcher.$dispatcherAlias");
                    $container->registerAliasForArgument($dispatcherAlias, MessageDispatcher::class);
                }
            }
        } else {
            $container->registerAttributeForAutoconfiguration(
                AsMessageConsumer::class,
                static function (ChildDefinition $definition, AsMessageConsumer $attribute): void {
                    $dispatcherAlias = $attribute->dispatcher;
                    $definition->addTag("andreo.event_sauce.message_consumer.$dispatcherAlias");
                }
            );

            foreach ($chainConfig as $dispatcherAlias) {
                $container
                    ->register("andreo.event_sauce.message_dispatcher.$dispatcherAlias", SynchronousMessageDispatcher::class)
                    ->setFactory([SynchronousMessageDispatcherFactory::class, 'create'])
                    ->addArgument(new TaggedIteratorArgument("andreo.event_sauce.message_consumer.$dispatcherAlias"))
                    ->setPublic(false)
                ;
                if ($eventDispatcherEnabled) {
                    $this->registerEventDispatcher($container, $dispatcherAlias);
                } else {
                    $container->setAlias($dispatcherAlias, "andreo.event_sauce.message_dispatcher.$dispatcherAlias");
                    $container->registerAliasForArgument($dispatcherAlias, MessageDispatcher::class);
                }
            }
        }
    }

    private function registerEventDispatcher(ContainerBuilder $container, string $dispatcherAlias): void
    {
        $container
            ->register("andreo.event_sauce.event_dispatcher.$dispatcherAlias", MessageDispatchingEventDispatcher::class)
            ->setArguments([
                new Reference("andreo.event_sauce.message_dispatcher.$dispatcherAlias"),
                new Reference('andreo.event_sauce.event_dispatcher_message_decorator_chain'),
            ])
            ->setPublic(false)
        ;
        $container->setAlias($dispatcherAlias, "andreo.event_sauce.event_dispatcher.$dispatcherAlias");
        $container->registerAliasForArgument($dispatcherAlias, EventDispatcher::class);
    }

    private function loadOutbox(ContainerBuilder $container, YamlFileLoader $loader, array $config): void
    {
        $outboxConfig = $config['outbox'];
        if (!$this->isConfigEnabled($container, $outboxConfig)) {
            return;
        }
        if (!class_exists(EventSourcedAggregateRootRepositoryForOutbox::class)) {
            throw new LogicException('Message outbox is not available. Try running "composer require andreo/eventsauce-outbox".');
        }

        $loader->load('outbox.yaml');

        $initialDelayMsParam = '%andreo.event_sauce.outbox.back_off.initial_delay_ms%';
        $maxTriesParam = '%andreo.event_sauce.outbox.back_off.max_tries%';

        $backOffConfig = $outboxConfig['back_off'];
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
        } elseif ($this->isConfigEnabled($container, $linearBackConfig = $backOffConfig['linear'])) {
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
        } elseif ($this->isConfigEnabled($container, $backOffConfig['immediately'])) {
            $container->setAlias(BackOffStrategy::class, ImmediatelyFailingBackOffStrategy::class);
        } elseif ($this->isConfigEnabled($container, $customConfig = $backOffConfig['custom'])) {
            $container->setAlias(BackOffStrategy::class, $customConfig['id']);
        }

        $relayCommitConfig = $outboxConfig['relay_commit'];
        if ($this->isConfigEnabled($container, $relayCommitConfig['delete'])) {
            $container->setAlias(RelayCommitStrategy::class, DeleteMessageOnCommit::class);
        }

        if (null !== $loggerAlias = $outboxConfig['logger']) {
            $processMessagesCommandDef = $container->getDefinition(OutboxProcessMessagesCommand::class);
            $processMessagesCommandDef->replaceArgument(1, new Reference($loggerAlias));
        }
    }

    private function loadSnapshot(ContainerBuilder $container, YamlFileLoader $loader, array $config): void
    {
        $snapshotConfig = $config['snapshot'];
        if (!$this->isConfigEnabled($container, $snapshotConfig)) {
            return;
        }

        $needLoad = false;

        $snapshotRepositoryConfig = $snapshotConfig['repository'];
        if ($snapshotDoctrineRepositoryEnabled = $this->isConfigEnabled($container, $snapshotRepositoryConfig['doctrine'])) {
            if (!class_exists(DoctrineSnapshotRepository::class)) {
                throw new LogicException('Doctrine snapshot repository is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        $snapshotSerializerId = $snapshotConfig['serializer'];
        if ($snapshotDoctrineRepositoryEnabled &&
            !in_array($snapshotSerializerId, [null, SnapshotStateSerializer::class, ConstructingSnapshotStateSerializer::class], true)) {
            $container->setAlias(SnapshotStateSerializer::class, $snapshotSerializerId);
        }

        $storeStrategyConfig = $snapshotConfig['store_strategy'];
        if ($this->isConfigEnabled($container, $storeStrategyConfig['every_n_event'])) {
            if (!interface_exists(CanStoreSnapshotStrategy::class)) {
                throw new LogicException('Store snapshot strategy is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        if ($snapshotConfig['versioned']) {
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
        if ('message' === $upcastConfig['context'] && !interface_exists(MessageUpcaster::class)) {
            throw new LogicException('Message upcaster is not available. Try running "composer require andreo/eventsauce-upcasting".');
        }

        $container->registerAttributeForAutoconfiguration(
            AsUpcaster::class,
            static function (ChildDefinition $definition, AsUpcaster $attribute): void {
                $aggregateName = $attribute->aggregate;
                $definition->addTag("andreo.event_sauce.upcaster.$aggregateName", ['priority' => -$attribute->version]);
            }
        );
    }

    private function loadPayloadSerializer(ContainerBuilder $container, YamlFileLoader $loader, array $config): void
    {
        $payloadSerializer = $config['payload_serializer'];
        if (SymfonyPayloadSerializer::class === $payloadSerializer) {
            if (!class_exists(SymfonyPayloadSerializer::class)) {
                throw new LogicException('Symfony payload serializer is not available. Try running "composer require andreo/eventsauce-symfony-serializer".');
            }

            $loader->load('symfony_serializer.yaml');
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
        if (!class_exists(DoctrineMigrationsBundle::class)) {
            throw new LogicException('Generate migration library require doctrine migration bundle. Try running "composer require doctrine/doctrine-migrations-bundle".');
        }

        $messageDoctrineConfig = $config['message']['repository']['doctrine'];
        $eventTableName = $messageDoctrineConfig['table_name'];

        $snapshotDoctrineConfig = $config['snapshot']['repository']['doctrine'];
        $snapshotTableName = $snapshotDoctrineConfig['table_name'];

        $outboxDoctrineConfig = $config['outbox']['repository']['doctrine'];
        $outboxTableName = $outboxDoctrineConfig['table_name'];

        $container
            ->register(TableNameSuffix::class, TableNameSuffix::class)
            ->setArguments([
                $eventTableName,
                $outboxTableName,
                $snapshotTableName,
            ])
            ->setPublic(false)
        ;

        $loader->load('migration.yaml');
    }

    private function loadAggregates(array $config, ContainerBuilder $container): void
    {
        $messageConfig = $config['message'];
        $snapshotConfig = $config['snapshot'];
        $upcastConfig = $config['upcast'];

        foreach ($config['aggregates'] as $aggregateName => $aggregateConfig) {
            $aggregateConfig['repository_alias'] ??= sprintf('%sRepository', $aggregateName);

            $this->loadAggregateDispatchers(
                $container,
                $aggregateName,
                $aggregateConfig,
                $config,
            );

            $this->loadAggregateMessageRepository(
                $container,
                $aggregateName,
                $aggregateConfig,
                $messageConfig,
                $upcastConfig
            );

            $this->loadAggregateRepository(
                $container,
                $aggregateName,
                $aggregateConfig
            );

            if ($this->isConfigEnabled($container, $aggregateConfig['outbox'])) {
                $this->loadAggregateOutboxRepository(
                    $container,
                    $aggregateName,
                    $aggregateConfig,
                    $config['outbox']
                );
            }

            if ($this->isConfigEnabled($container, $aggregateConfig['snapshot'])) {
                $this->loadAggregateSnapshotRepository(
                    $container,
                    $aggregateName,
                    $aggregateConfig,
                    $snapshotConfig
                );
            }
        }
    }

    private function loadAggregateDispatchers(
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig,
        array $config
    ): void {
        $messageConfig = $config['message'];
        $messageDispatcherConfig = $messageConfig['dispatcher'];
        $dispatcherChain = array_keys($messageDispatcherConfig['chain']);
        $aggregateDispatchers = $aggregateConfig['dispatchers'];

        $messageDispatcherRefers = [];
        $aggregateDispatchers = empty($aggregateDispatchers) ? $dispatcherChain : $aggregateDispatchers;
        foreach ($aggregateDispatchers as $aggregateDispatcherAlias) {
            if (!in_array($aggregateDispatcherAlias, $dispatcherChain, true)) {
                throw new LogicException(sprintf('Dispatcher with name "%s" is not configured. Configure it in the message section.', $aggregateDispatcherAlias));
            }
            $messageDispatcherRefers[] = new Reference("andreo.event_sauce.message_dispatcher.$aggregateDispatcherAlias");
        }

        $container
            ->register(
            "andreo.event_sauce.message_dispatcher_chain.$aggregateName",
                MessageDispatcherChain::class
        )
            ->setFactory([MessageDispatcherChainFactory::class, 'create'])
            ->addArgument(new IteratorArgument($messageDispatcherRefers))
            ->setPublic(false)
        ;
    }

    private function loadAggregateMessageRepository(
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig,
        array $messageConfig,
        array $upcastConfig
    ): void {
        $messageRepositoryConfig = $messageConfig['repository'];
        $messageRepositoryDoctrineConfig = $messageRepositoryConfig['doctrine'];
        $jsonEncodeOptions = $messageRepositoryConfig['json_encode_options'];
        $messageTableName = $messageRepositoryDoctrineConfig['table_name'];

        if ($this->isConfigEnabled($container, $aggregateConfig['upcast'])) {
            if (!$this->isConfigEnabled($container, $upcastConfig)) {
                throw new LogicException('Upcast config is disabled. If you want to use it, enable and configure it .');
            }

            $context = $upcastConfig['context'];
            if ('payload' === $context) {
                if (!class_exists(UpcasterChainWithEventGuessing::class)) {
                    $upcasterChainDef = (new Definition(UpcasterChain::class, [
                        new TaggedIteratorArgument("andreo.event_sauce.upcaster.$aggregateName"),
                    ]))->setFactory([UpcasterChainFactory::class, 'create']);
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
                $upcasterChainDef = (new Definition(MessageUpcasterChain::class, [
                    new TaggedIteratorArgument("andreo.event_sauce.upcaster.$aggregateName"),
                ]))->setFactory([MessageUpcasterChainFactory::class, 'create']);

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

    private function loadAggregateRepository(
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig
    ): void {
        $aggregateClass = $aggregateConfig['class'];
        $repositoryAlias = $aggregateConfig['repository_alias'];

        $container
            ->register("andreo.event_sauce.aggregate_repository.$aggregateName", EventSourcedAggregateRootRepository::class)
            ->setArguments([
                $aggregateClass,
                new Reference("andreo.event_sauce.message_repository.$aggregateName"),
                new Reference("andreo.event_sauce.message_dispatcher_chain.$aggregateName"),
                new Reference('andreo.event_sauce.aggregate_message_decorator_chain'),
                new Reference(ClassNameInflector::class),
            ])
            ->setPublic(false)
        ;

        $container->setAlias($repositoryAlias, "andreo.event_sauce.aggregate_repository.$aggregateName");
        $container->registerAliasForArgument($repositoryAlias, AggregateRootRepository::class);
    }

    private function loadAggregateOutboxRepository(
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig,
        array $outboxConfig
    ): void {
        $outboxRepositoryConfig = $outboxConfig['repository'];
        $repositoryAlias = $aggregateConfig['repository_alias'];
        $aggregateClass = $aggregateConfig['class'];

        if (!$this->isConfigEnabled($container, $outboxConfig)) {
            throw new LogicException('Message outbox config is disabled. If you want to use it, enable and configure it .');
        }

        $memoryRepositoryEnabled = $this->isConfigEnabled($container, $outboxRepositoryConfig['memory']);
        $doctrineRepositoryEnabled = $this->isConfigEnabled($container, $outboxRepositoryDoctrineConfig = $outboxRepositoryConfig['doctrine']);

        if ($doctrineRepositoryEnabled || !$memoryRepositoryEnabled) {
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
            $container
                ->register("andreo.event_sauce.outbox_repository.$aggregateName", InMemoryOutboxRepository::class)
                ->setPublic(false)
            ;
        }

        $regularMessageRepositoryDef = $container->getDefinition("andreo.event_sauce.message_repository.$aggregateName");
        $container
            ->register("andreo.event_sauce.message_repository.$aggregateName", DoctrineTransactionalMessageRepository::class)
            ->setArguments([
                new Reference('andreo.event_sauce.doctrine.connection'),
                $regularMessageRepositoryDef,
                new Reference("andreo.event_sauce.outbox_repository.$aggregateName"),
            ])
            ->setPublic(false)
        ;

        $regularAggregateRepositoryDef = $container->getDefinition("andreo.event_sauce.aggregate_repository.$aggregateName");
        $container
            ->register("andreo.event_sauce.aggregate_repository.$aggregateName", EventSourcedAggregateRootRepositoryForOutbox::class)
            ->setArguments([
                $aggregateClass,
                new Reference("andreo.event_sauce.message_repository.$aggregateName"),
                $regularAggregateRepositoryDef,
                new Reference('andreo.event_sauce.aggregate_message_decorator_chain'),
                new Reference(ClassNameInflector::class),
            ])
            ->setPublic(false)
        ;

        $container->setAlias($repositoryAlias, "andreo.event_sauce.aggregate_repository.$aggregateName");
        $container->registerAliasForArgument($repositoryAlias, AggregateRootRepository::class);

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
            ->addTag('andreo.event_sauce.outbox_relay', [
                'name' => "outbox_relay_$aggregateName",
            ])
            ->setPublic(false)
        ;
    }

    private function loadAggregateSnapshotRepository(
        ContainerBuilder $container,
        string $aggregateName,
        array $aggregateConfig,
        array $snapshotConfig
    ): void {
        if (!$this->isConfigEnabled($container, $snapshotConfig)) {
            throw new LogicException(sprintf('To use snapshot for aggregate "%s", you must enable snapshot in main section.', $aggregateName));
        }

        $aggregateClass = $aggregateConfig['class'];
        $snapshotRepositoryConfig = $snapshotConfig['repository'];
        $repositoryAlias = $aggregateConfig['repository_alias'];
        $storeStrategyConfig = $snapshotConfig['store_strategy'];

        $snapshotMemoryRepositoryEnabled = $this->isConfigEnabled($container, $snapshotRepositoryConfig['memory']);
        $snapshotDoctrineRepositoryEnabled = $this->isConfigEnabled($container, $snapshotDoctrineRepositoryConfig = $snapshotRepositoryConfig['doctrine']);

        if ($snapshotMemoryRepositoryEnabled || !$snapshotDoctrineRepositoryEnabled) {
            $snapshotRepositoryDef = new Definition(InMemorySnapshotRepository::class);
        } else {
            $tableName = sprintf('%s_%s', $aggregateName, $snapshotDoctrineRepositoryConfig['table_name']);
            $snapshotRepositoryDef = new Definition(DoctrineSnapshotRepository::class, [
                new Reference('andreo.event_sauce.doctrine.connection'),
                $tableName,
                new Reference(SnapshotStateSerializer::class),
                new Reference(UuidEncoder::class),
            ]);
        }

        $regularRepositoryDef = $container->getDefinition("andreo.event_sauce.aggregate_repository.$aggregateName");
        if ($snapshotConfig['versioned']) {
            $snapshottingRepositoryDef = new Definition(AggregateRootRepositoryWithVersionedSnapshotting::class, [
                $aggregateClass,
                new Reference("andreo.event_sauce.message_repository.$aggregateName"),
                $snapshotRepositoryDef,
                $regularRepositoryDef,
            ]);
        } else {
            $snapshottingRepositoryDef = new Definition(ConstructingAggregateRootRepositoryWithSnapshotting::class, [
                $aggregateClass,
                new Reference("andreo.event_sauce.message_repository.$aggregateName"),
                $snapshotRepositoryDef,
                $regularRepositoryDef,
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
            ->setDefinition("andreo.event_sauce.aggregate_repository.$aggregateName", $snapshottingRepositoryDef)
            ->setPublic(false)
        ;

        $container->setAlias($repositoryAlias, "andreo.event_sauce.aggregate_repository.$aggregateName");
        $container->registerAliasForArgument($repositoryAlias, AggregateRootRepositoryWithSnapshotting::class);
    }
}
