<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Outbox\MessageConsumer\ForwardingMessageConsumer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\EventSourcing\EventDispatcher;
use EventSauce\EventSourcing\MessageDispatchingEventDispatcher;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineOutboxRepository;
use EventSauce\MessageOutbox\InMemoryOutboxRepository;
use EventSauce\MessageOutbox\OutboxMessageDispatcher;
use EventSauce\MessageOutbox\OutboxRelay;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

final readonly class EventDispatcherLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        array $config
    ): void {
        $eventDispatcherConfig = $config['event_dispatcher'];
        if (!$extension->isConfigEnabled($container, $eventDispatcherConfig)) {
            return;
        }

        $outboxConfig = $config['message_outbox'];
        if ($extension->isConfigEnabled($container, $eventDispatcherOutboxConfig = $eventDispatcherConfig['message_outbox'])) {
            if (!$extension->isConfigEnabled($container, $outboxConfig)) {
                throw new LogicException('Message Outbox config is disabled.');
            }

            $outboxRepositoryConfig = $outboxConfig['repository'];
            $doctrineRepositoryEnabled = $extension->isConfigEnabled($container, $outboxRepositoryConfig['doctrine']);

            if ($doctrineRepositoryEnabled) {
                $tableName = $eventDispatcherOutboxConfig['table_name'];
                $outboxRepositoryDef = new Definition(DoctrineOutboxRepository::class, [
                    new Reference('andreo.eventsauce.doctrine.connection'),
                    $tableName,
                    new Reference(MessageSerializer::class),
                ]);
            } else {
                $outboxRepositoryDef = new Definition(InMemoryOutboxRepository::class);
            }

            $messageDispatcherArgument = new Definition(OutboxMessageDispatcher::class, [
                $outboxRepositoryDef,
            ]);

            $messageConsumerDefinition = new Definition(ForwardingMessageConsumer::class, [
                new Reference('andreo.eventsauce.message_dispatcher_chain'),
            ]);

            $container
                ->register('andreo.eventsauce.event_dispatcher.outbox_relay', OutboxRelay::class)
                ->setArguments([
                    $outboxRepositoryDef,
                    $messageConsumerDefinition,
                    new Reference(BackOffStrategy::class),
                    new Reference(RelayCommitStrategy::class),
                ])
                ->addTag('andreo.eventsauce.outbox_relay', [
                    'relay_id' => $eventDispatcherOutboxConfig['relay_id'],
                ])
                ->setPublic(false)
            ;
        } else {
            $messageDispatcherArgument = new Reference('andreo.eventsauce.message_dispatcher_chain');
        }

        $container
            ->register(MessageDispatchingEventDispatcher::class, MessageDispatchingEventDispatcher::class)
            ->setArguments([
                $messageDispatcherArgument,
                new Reference('andreo.eventsauce.message_decorator_chain'),
            ])
            ->setPublic(false)
        ;

        $container->setAlias(EventDispatcher::class, MessageDispatchingEventDispatcher::class);
    }
}
