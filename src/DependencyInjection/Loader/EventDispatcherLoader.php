<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\EventDispatcher;
use EventSauce\EventSourcing\MessageDispatchingEventDispatcher;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineOutboxRepository;
use EventSauce\MessageOutbox\InMemoryOutboxRepository;
use EventSauce\MessageOutbox\OutboxMessageDispatcher;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class EventDispatcherLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $eventDispatcherConfig = $config['event_dispatcher'];
        if (!$this->extension->isConfigEnabled($this->container, $eventDispatcherConfig)) {
            return;
        }

        $eventDispatcherOutboxConfig = $eventDispatcherConfig['outbox'];
        $outboxConfig = $config['outbox'];

        if ($this->extension->isConfigEnabled($this->container, $eventDispatcherOutboxConfig)) {
            if (!$this->extension->isConfigEnabled($this->container, $outboxConfig)) {
                throw new LogicException('Message default outbox config is disabled. If you want to use it, enable and configure it .');
            }

            $outboxRepositoryConfig = $eventDispatcherOutboxConfig['repository'];
            $memoryRepositoryEnabled = $this->extension->isConfigEnabled($this->container, $outboxRepositoryConfig['memory']);
            $doctrineRepositoryEnabled = $this->extension->isConfigEnabled($this->container, $outboxRepositoryDoctrineConfig = $outboxRepositoryConfig['doctrine']);

            if ($doctrineRepositoryEnabled || !$memoryRepositoryEnabled) {
                $tableName = $outboxRepositoryDoctrineConfig['table_name'];
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
        } else {
            $messageDispatcherArgument = new Reference('andreo.eventsauce.message_dispatcher_chain');
        }

        $this->container
            ->register(MessageDispatchingEventDispatcher::class, MessageDispatchingEventDispatcher::class)
            ->setArguments([
                $messageDispatcherArgument,
                new Reference('andreo.eventsauce.message_decorator_chain'),
            ])
            ->setPublic(false)
        ;

        $this->container->setAlias(EventDispatcher::class, MessageDispatchingEventDispatcher::class);
    }
}
