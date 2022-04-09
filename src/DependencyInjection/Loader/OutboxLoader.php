<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Outbox\EventSourcedAggregateRootRepositoryForOutbox;
use Andreo\EventSauce\Outbox\OutboxProcessMessagesCommand;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\BackOff\ExponentialBackOffStrategy;
use EventSauce\BackOff\FibonacciBackOffStrategy;
use EventSauce\BackOff\ImmediatelyFailingBackOffStrategy;
use EventSauce\BackOff\LinearBackOffStrategy;
use EventSauce\BackOff\NoWaitingBackOffStrategy;
use EventSauce\MessageOutbox\DeleteMessageOnCommit;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class OutboxLoader
{
    public function __construct(
        private AndreoEventSauceExtension $extension,
        private YamlFileLoader $loader,
        private ContainerBuilder $container
    ) {
    }

    public function __invoke(array $config): void
    {
        $outboxConfig = $config['outbox'];
        if (!$this->extension->isConfigEnabled($this->container, $outboxConfig)) {
            return;
        }
        if (!class_exists(EventSourcedAggregateRootRepositoryForOutbox::class)) {
            throw new LogicException('Message outbox is not available. Try running "composer require andreo/eventsauce-outbox".');
        }

        $this->loader->load('outbox.yaml');

        $initialDelayMsParam = '%andreo.eventsauce.outbox.back_off.initial_delay_ms%';
        $maxTriesParam = '%andreo.eventsauce.outbox.back_off.max_tries%';

        $backOffConfig = $outboxConfig['back_off'];
        if ($this->extension->isConfigEnabled($this->container, $exponentialConfig = $backOffConfig['exponential'])) {
            $initialDelayMs = $exponentialConfig['initial_delay_ms'];
            $maxTries = $exponentialConfig['max_tries'];
            if (null !== $initialDelayMs || null !== $maxTries) {
                $this->container
                    ->getDefinition(ExponentialBackOffStrategy::class)
                    ->replaceArgument(0, $initialDelayMs ?? $initialDelayMsParam)
                    ->replaceArgument(1, $maxTries ?? $maxTriesParam)
                ;
            }
        } elseif ($this->extension->isConfigEnabled($this->container, $fibonacciConfig = $backOffConfig['fibonacci'])) {
            $initialDelayMs = $fibonacciConfig['initial_delay_ms'];
            $maxTries = $fibonacciConfig['max_tries'];
            if (null !== $initialDelayMs || null !== $maxTries) {
                $this->container
                    ->getDefinition(FibonacciBackOffStrategy::class)
                    ->replaceArgument(1, $maxTries ?? $maxTriesParam)
                ;
            }
            $this->container->setAlias(BackOffStrategy::class, FibonacciBackOffStrategy::class);
        } elseif ($this->extension->isConfigEnabled($this->container, $linearBackConfig = $backOffConfig['linear'])) {
            $initialDelayMs = $linearBackConfig['initial_delay_ms'];
            $maxTries = $linearBackConfig['max_tries'];
            if (null !== $initialDelayMs || null !== $maxTries) {
                $this->container
                    ->getDefinition(LinearBackOffStrategy::class)
                    ->replaceArgument(0, $initialDelayMs ?? $initialDelayMsParam)
                    ->replaceArgument(1, $maxTries ?? $maxTriesParam)
                ;
            }
            $this->container->setAlias(BackOffStrategy::class, LinearBackOffStrategy::class);
        } elseif ($this->extension->isConfigEnabled($this->container, $noWaitingConfig = $backOffConfig['no_waiting'])) {
            $maxTries = $noWaitingConfig['max_tries'];
            if (null !== $maxTries) {
                $this->container
                    ->getDefinition(NoWaitingBackOffStrategy::class)
                    ->replaceArgument(0, $maxTries)
                ;
            }
            $this->container->setAlias(BackOffStrategy::class, NoWaitingBackOffStrategy::class);
        } elseif ($this->extension->isConfigEnabled($this->container, $backOffConfig['immediately'])) {
            $this->container->setAlias(BackOffStrategy::class, ImmediatelyFailingBackOffStrategy::class);
        } elseif ($this->extension->isConfigEnabled($this->container, $customConfig = $backOffConfig['custom'])) {
            $this->container->setAlias(BackOffStrategy::class, $customConfig['id']);
        }

        $relayCommitConfig = $outboxConfig['relay_commit'];
        if ($this->extension->isConfigEnabled($this->container, $relayCommitConfig['delete'])) {
            $this->container->setAlias(RelayCommitStrategy::class, DeleteMessageOnCommit::class);
        }

        if (null !== $loggerAlias = $outboxConfig['logger']) {
            $processMessagesCommandDef = $this->container->getDefinition(OutboxProcessMessagesCommand::class);
            $processMessagesCommandDef->replaceArgument(1, new Reference($loggerAlias));
        }
    }
}
