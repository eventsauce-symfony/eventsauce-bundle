<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\CompilerPass;

use Andreo\EventSauceBundle\DependencyInjection\Utils\TaggedServicesSortTool;
use Andreo\EventSauceBundle\Enum\MessageFilterStrategy;
use Andreo\EventSauceBundle\Enum\MessageFilterTrigger;
use Andreo\EventSauceBundle\Factory\MatchAllMessageFiltersFactory;
use Andreo\EventSauceBundle\Factory\MatchAnyMessageFiltersFactory;
use Andreo\EventSauceBundle\Factory\MessageTranslatorChainFactory;
use EventSauce\EventSourcing\AntiCorruptionLayer\AllowAllMessages;
use EventSauce\EventSourcing\AntiCorruptionLayer\AntiCorruptionMessageConsumer;
use EventSauce\EventSourcing\AntiCorruptionLayer\AntiCorruptionMessageDispatcher;
use EventSauce\EventSourcing\AntiCorruptionLayer\MatchAllMessageFilters;
use EventSauce\EventSourcing\AntiCorruptionLayer\MatchAnyMessageFilter;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslatorChain;
use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\MessageDispatcher;
use LogicException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Throwable;

final readonly class AclPass implements CompilerPassInterface
{
    public function __construct(
        private string $enablingParameter = 'andreo.eventsauce.acl_enabled',
        private string $aclTag = 'andreo.eventsauce.acl',
        private string $translatorTag = 'andreo.eventsauce.acl.message_translator',
        private string $filterTag = 'andreo.eventsauce.acl.message_filter',
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter($this->enablingParameter) || !$container->getParameter($this->enablingParameter)) {
            return;
        }

        [
            $commonTranslators,
            $dispatcherTranslators,
            $consumerTranslators

        ] = $this->resolveMessageTranslators($container);

        [
            $commonBeforeFilters,
            $dispatcherBeforeFilters,
            $consumerBeforeFilters,
            $commonAfterFilters,
            $dispatcherAfterFilters,
            $consumerAfterFilters

        ] = $this->resolveMessageFilters($container);

        foreach ($container->findTaggedServiceIds($this->aclTag, true) as $dispatcherOrConsumerId => [$attrs]) {
            $dispatcherOrConsumerDef = $container->findDefinition($dispatcherOrConsumerId);
            $dispatcherOrConsumerDefClass = $dispatcherOrConsumerDef->getClass() ?? $dispatcherOrConsumerId;
            if (!class_exists($dispatcherOrConsumerDefClass)) {
                continue;
            }

            if (is_subclass_of($dispatcherOrConsumerDefClass, MessageDispatcher::class)) {
                $this->processDispatcherAcl(
                    $container,
                    $dispatcherOrConsumerId,
                    $attrs,
                    $commonTranslators,
                    $dispatcherTranslators,
                    $commonBeforeFilters,
                    $dispatcherBeforeFilters,
                    $commonAfterFilters,
                    $dispatcherAfterFilters
                );
            } elseif (is_subclass_of($dispatcherOrConsumerDefClass, MessageConsumer::class)) {
                $this->processConsumerAcl(
                    $container,
                    $dispatcherOrConsumerId,
                    $attrs,
                    $commonTranslators,
                    $consumerTranslators,
                    $commonBeforeFilters,
                    $consumerBeforeFilters,
                    $commonAfterFilters,
                    $consumerAfterFilters
                );
            } else {
                throw new RuntimeException(sprintf('Service with enabled acl must be an %s or %s implementation.', MessageDispatcher::class, MessageConsumer::class));
            }
        }
    }

    private function makeAclDispatcherOrConsumerAndIndexAndFilterOrTranslatorMap(
        array $dispatcherMap,
        array $consumerMap,
        ContainerBuilder $container,
        array $owners,
        int $index,
        Reference $translatorOrFilterRef
    ): array {
        foreach ($owners as $owner) {
            if (MessageDispatcher::class === $owner) {
                $dispatcherMap[MessageDispatcher::class][$index] = $translatorOrFilterRef;
            } elseif (MessageConsumer::class === $owner) {
                $consumerMap[MessageConsumer::class][$index] = $translatorOrFilterRef;
            } elseif ($container->hasDefinition($owner)) {
                $ownerDef = $container->findDefinition($owner);
                $ownerClass = $ownerDef->getClass() ?? $owner;
                if (!class_exists($ownerClass)) {
                    continue;
                }

                if (is_subclass_of($ownerClass, MessageDispatcher::class)) {
                    $dispatcherMap[$owner][$index] = $translatorOrFilterRef;
                } elseif (is_subclass_of($ownerClass, MessageConsumer::class)) {
                    $consumerMap[$owner][$index] = $translatorOrFilterRef;
                } else {
                    throw new RuntimeException(sprintf('%s acl owner must be an %s or %s implementation.', $owner, MessageDispatcher::class, MessageConsumer::class));
                }
            } else {
                throw new RuntimeException(sprintf('%s definition does not exits.', $owner));
            }
        }

        return [$dispatcherMap, $consumerMap];
    }

    private function resolveMessageTranslators(ContainerBuilder $container): array
    {
        $commonTranslators = [];
        $dispatcherTranslators = [];
        $consumerTranslators = [];

        foreach (TaggedServicesSortTool::findAndSort($container, $this->translatorTag) as $index => $translatorReference) {
            $translatorDef = $container->findDefinition($translatorReference->__toString());

            [$tagAttrs] = $translatorDef->getTag($this->translatorTag);
            $owners = $tagAttrs['owners'] ?? [];
            $owners = is_string($owners) ? [$owners] : $owners;

            if (empty($owners)) {
                $commonTranslators[$index] = $translatorReference;
                continue;
            }

            [
                $dispatcherTranslators,
                $consumerTranslators
            ] = $this->makeAclDispatcherOrConsumerAndIndexAndFilterOrTranslatorMap(
                $dispatcherTranslators,
                $consumerTranslators,
                $container,
                $owners,
                $index,
                $translatorReference
            );
        }

        return [
            $commonTranslators,
            $dispatcherTranslators,
            $consumerTranslators,
        ];
    }

    private function resolveMessageFilters(ContainerBuilder $container): array
    {
        $commonBeforeFilters = [];
        $dispatcherBeforeFilters = [];
        $consumerBeforeFilters = [];

        $commonAfterFilters = [];
        $dispatcherAfterFilters = [];
        $consumerAfterFilters = [];

        foreach (TaggedServicesSortTool::findAndSort($container, $this->filterTag) as $index => $filterReference) {
            $filterDef = $container->findDefinition($filterReference->__toString());
            [$tagAttrs] = $filterDef->getTag($this->filterTag);
            $owners = $tagAttrs['owners'] ?? [];
            $owners = is_string($owners) ? [$owners] : $owners;
            try {
                $trigger = MessageFilterTrigger::from($tagAttrs['trigger']);
            } catch (Throwable) {
                throw new RuntimeException('Valid trigger must be configured.');
            }

            if ($trigger->identity(MessageFilterTrigger::BEFORE_TRANSLATE)) {
                if (empty($owners)) {
                    $commonBeforeFilters[$index] = $filterReference;
                    continue;
                }

                [
                    $dispatcherBeforeFilters,
                    $consumerBeforeFilters
                ] = $this->makeAclDispatcherOrConsumerAndIndexAndFilterOrTranslatorMap(
                    $dispatcherBeforeFilters,
                    $consumerBeforeFilters,
                    $container,
                    $owners,
                    $index,
                    $filterReference
                );
            } elseif ($trigger->identity(MessageFilterTrigger::AFTER_TRANSLATE)) {
                if (empty($owners)) {
                    $commonAfterFilters[$index] = $filterReference;
                    continue;
                }

                [
                    $dispatcherAfterFilters,
                    $consumerAfterFilters
                ] = $this->makeAclDispatcherOrConsumerAndIndexAndFilterOrTranslatorMap(
                    $dispatcherAfterFilters,
                    $consumerAfterFilters,
                    $container,
                    $owners,
                    $index,
                    $filterReference
                );
            } else {
                throw new LogicException();
            }
        }

        return [
            $commonBeforeFilters,
            $dispatcherBeforeFilters,
            $consumerBeforeFilters,
            $commonAfterFilters,
            $dispatcherAfterFilters,
            $consumerAfterFilters,
        ];
    }

    private function processDispatcherAcl(
        ContainerBuilder $container,
        string $dispatcherId,
        array $tagAttrs,
        array $commonTranslators,
        array $dispatcherTranslators,
        array $commonBeforeFilters,
        array $dispatcherBeforeFilters,
        array $commonAfterFilters,
        array $dispatcherAfterFilters
    ): void {
        [
            $messageTranslatorChain,
            $filterBefore,
            $filterAfter

        ] = $this->makeAclArguments(
            MessageDispatcher::class,
            $dispatcherId,
            $tagAttrs,
            $commonTranslators,
            $dispatcherTranslators,
            $commonBeforeFilters,
            $dispatcherBeforeFilters,
            $commonAfterFilters,
            $dispatcherAfterFilters
        );

        $container
            ->register($newDispatcherId = sprintf('%s.acl', $dispatcherId), AntiCorruptionMessageDispatcher::class)
            ->setArguments([
                new Reference(sprintf('%s.inner', $newDispatcherId)),
                $messageTranslatorChain,
                $filterBefore,
                $filterAfter,
            ])
            ->setDecoratedService($dispatcherId)
            ->setPublic(false)
        ;
    }

    private function makeAclArguments(
        string $dispatcherOrConsumerType,
        string $dispatcherOrConsumerId,
        array $tagAttrs,
        array $commonTranslators,
        array $dispatcherOrConsumerTranslators,
        array $commonBeforeFilters,
        array $dispatcherOrConsumerBeforeFilters,
        array $commonAfterFilters,
        array $dispatcherOrConsumerAfterFilters
    ): array {
        $translators = $commonTranslators +
            ($dispatcherOrConsumerTranslators[$dispatcherOrConsumerType] ?? []) +
            ($dispatcherOrConsumerTranslators[$dispatcherOrConsumerId] ?? [])
        ;

        ksort($translators);
        $translators = array_values($translators);

        $messageTranslatorChain = (new Definition(MessageTranslatorChain::class, [
            new IteratorArgument($translators),
        ]))->setFactory([MessageTranslatorChainFactory::class, 'create']);

        $allowAllDef = new Definition(AllowAllMessages::class);
        $filterBefore = $allowAllDef;
        $filterAfter = $allowAllDef;

        try {
            $messageFilterStrategyBeforeTranslate = MessageFilterStrategy::from($tagAttrs['message_filter_strategy_before_translate']);
        } catch (Throwable) {
            throw new RuntimeException('Valid filter strategy must be configured.');
        }

        $beforeFilters = $commonBeforeFilters +
            ($dispatcherOrConsumerBeforeFilters[$dispatcherOrConsumerType] ?? []) +
            ($dispatcherOrConsumerBeforeFilters[$dispatcherOrConsumerId] ?? [])
        ;

        ksort($beforeFilters);
        $beforeFilters = array_values($beforeFilters);

        if ($messageFilterStrategyBeforeTranslate->identity(MessageFilterStrategy::MATCH_ALL)) {
            $filterBefore = (new Definition(MatchAllMessageFilters::class, [
                new IteratorArgument($beforeFilters),
            ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
        } elseif ($messageFilterStrategyBeforeTranslate->identity(MessageFilterStrategy::MATCH_ANY)) {
            $filterBefore = (new Definition(MatchAnyMessageFilter::class, [
                new IteratorArgument($beforeFilters),
            ]))->setFactory([MatchAnyMessageFiltersFactory::class, 'create']);
        }

        try {
            $messageFilterStrategyAfterTranslate = MessageFilterStrategy::from($tagAttrs['message_filter_strategy_after_translate']);
        } catch (Throwable) {
            throw new RuntimeException('Valid filter strategy must be configured.');
        }

        $afterFilters = $commonAfterFilters +
            ($dispatcherOrConsumerAfterFilters[$dispatcherOrConsumerType] ?? []) +
            ($dispatcherOrConsumerAfterFilters[$dispatcherOrConsumerId] ?? [])
        ;

        ksort($afterFilters);
        $afterFilters = array_values($afterFilters);

        if ($messageFilterStrategyAfterTranslate->identity(MessageFilterStrategy::MATCH_ALL)) {
            $filterAfter = (new Definition(MatchAllMessageFilters::class, [
                new IteratorArgument($afterFilters),
            ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
        } elseif ($messageFilterStrategyAfterTranslate->identity(MessageFilterStrategy::MATCH_ANY)) {
            $filterAfter = (new Definition(MatchAnyMessageFilter::class, [
                new IteratorArgument($afterFilters),
            ]))->setFactory([MatchAnyMessageFiltersFactory::class, 'create']);
        }

        return [
            $messageTranslatorChain,
            $filterBefore,
            $filterAfter,
        ];
    }

    private function processConsumerAcl(
        ContainerBuilder $container,
        string $consumerId,
        array $tagAttrs,
        array $commonTranslators,
        array $consumerTranslators,
        array $commonBeforeFilters,
        array $consumerBeforeFilters,
        array $commonAfterFilters,
        array $consumerAfterFilters
    ): void {
        [
            $messageTranslatorChain,
            $filterBefore,
            $filterAfter

        ] = $this->makeAclArguments(
            MessageConsumer::class,
            $consumerId,
            $tagAttrs,
            $commonTranslators,
            $consumerTranslators,
            $commonBeforeFilters,
            $consumerBeforeFilters,
            $commonAfterFilters,
            $consumerAfterFilters
        );

        $container
            ->register($newConsumerId = sprintf('%s.acl', $consumerId), AntiCorruptionMessageConsumer::class)
            ->setArguments([
                new Reference(sprintf('%s.inner', $newConsumerId)),
                $messageTranslatorChain,
                $filterBefore,
                $filterAfter,
            ])
            ->setDecoratedService($consumerId)
            ->setPublic(false)
        ;
    }
}
