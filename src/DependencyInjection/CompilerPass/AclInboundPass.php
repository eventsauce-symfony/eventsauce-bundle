<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\CompilerPass;

use Andreo\EventSauceBundle\Factory\MatchAllMessageFiltersFactory;
use Andreo\EventSauceBundle\Factory\MatchAnyMessageFiltersFactory;
use Andreo\EventSauceBundle\Factory\MessageTranslatorChainFactory;
use EventSauce\EventSourcing\AntiCorruptionLayer\AllowAllMessages;
use EventSauce\EventSourcing\AntiCorruptionLayer\AntiCorruptionMessageConsumer;
use EventSauce\EventSourcing\AntiCorruptionLayer\MatchAllMessageFilters;
use EventSauce\EventSourcing\AntiCorruptionLayer\MatchAnyMessageFilter;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslatorChain;
use RuntimeException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AclInboundPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function __construct(private ?string $enablingParameter = null)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        if ($this->enablingParameter && (!$container->hasParameter($this->enablingParameter) || !$container->getParameter($this->enablingParameter))) {
            return;
        }

        $translators = [];
        $consumerTranslators = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_inbound.translator', $container) as $index => $translatorReference) {
            $translatorDef = $container->findDefinition($translatorReference->__toString());
            if (!$translatorDef->hasTag('andreo.eventsauce.acl_inbound_target')) {
                $translators[$index] = $translatorReference;
            } else {
                [$targetAttrs] = $translatorDef->getTag('andreo.eventsauce.acl_inbound_target');
                $translatorTargetId = $targetAttrs['id'] ?? null;
                if (null === $translatorTargetId) {
                    $translators[$index] = $translatorReference;
                    continue;
                }
                $this->checkTarget($container, $translatorTargetId, $translatorReference->__toString());
                $consumerTranslators[$translatorTargetId][$index] = $translatorReference;
            }
        }

        $beforeFilters = [];
        $consumerBeforeFilters = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_inbound.filter_before', $container) as $index => $filterBeforeReference) {
            $filterBeforeDef = $container->findDefinition($filterBeforeReference->__toString());
            if (!$filterBeforeDef->hasTag('andreo.eventsauce.acl_inbound_target')) {
                $beforeFilters[$index] = $filterBeforeReference;
            } else {
                [$targetAttrs] = $filterBeforeDef->getTag('andreo.eventsauce.acl_inbound_target');
                $filterBeforeTargetId = $targetAttrs['id'] ?? null;
                if (null === $filterBeforeTargetId) {
                    $beforeFilters[$index] = $filterBeforeReference;
                    continue;
                }
                $this->checkTarget($container, $filterBeforeTargetId, $filterBeforeReference->__toString());
                $consumerBeforeFilters[$filterBeforeTargetId][$index] = $filterBeforeReference;
            }
        }

        $afterFilters = [];
        $consumerAfterFilters = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_inbound.filter_after', $container) as $index => $filterAfterReference) {
            $filterAfterDef = $container->findDefinition($filterAfterReference->__toString());
            if (!$filterAfterDef->hasTag('andreo.eventsauce.acl_inbound_target')) {
                $afterFilters[$index] = $filterAfterReference;
            } else {
                [$targetAttrs] = $filterAfterDef->getTag('andreo.eventsauce.acl_inbound_target');
                $filterAfterTargetId = $targetAttrs['id'] ?? null;
                if (null === $filterAfterTargetId) {
                    $afterFilters[$index] = $filterAfterReference;
                    continue;
                }

                $this->checkTarget($container, $filterAfterTargetId, $filterAfterReference->__toString());
                $consumerAfterFilters[$filterAfterTargetId][$index] = $filterAfterReference;
            }
        }

        $allowAllDef = new Definition(AllowAllMessages::class);
        $filterBefore = $allowAllDef;
        $filterAfter = $allowAllDef;

        foreach ($container->findTaggedServiceIds('andreo.eventsauce.acl_inbound') as $consumerId => [$attrs]) {
            $inboundTranslators = $translators + ($consumerTranslators[$consumerId] ?? []);
            ksort($inboundTranslators);
            $inboundTranslators = array_unique(array_values($inboundTranslators), SORT_REGULAR);

            $messageTranslatorChain = (new Definition(MessageTranslatorChain::class, [
                new IteratorArgument($inboundTranslators),
            ]))->setFactory([MessageTranslatorChainFactory::class, 'create']);

            $consumerDef = $container->findDefinition($consumerId);
            if ($consumerDef->hasTag('andreo.eventsauce.acl.filter_strategy')) {
                [$targetAttrs] = $consumerDef->getTag('andreo.eventsauce.acl.filter_strategy');

                $filterStrategyBefore = $targetAttrs['before'];
                if ('match_all' === $filterStrategyBefore) {
                    $inboundBeforeFilters = $beforeFilters + ($consumerBeforeFilters[$consumerId] ?? []);
                    ksort($inboundBeforeFilters);
                    $inboundBeforeFilters = array_unique(array_values($inboundBeforeFilters), SORT_REGULAR);

                    $filterBefore = (new Definition(MatchAllMessageFilters::class, [
                        new IteratorArgument($inboundBeforeFilters),
                    ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
                } elseif ('match_any' === $filterStrategyBefore) {
                    $inboundBeforeFilters = $beforeFilters + ($consumerBeforeFilters[$consumerId] ?? []);
                    ksort($inboundBeforeFilters);
                    $inboundBeforeFilters = array_unique(array_values($inboundBeforeFilters), SORT_REGULAR);

                    $filterBefore = (new Definition(MatchAnyMessageFilter::class, [
                        new IteratorArgument($inboundBeforeFilters),
                    ]))->setFactory([MatchAnyMessageFiltersFactory::class, 'create']);
                } else {
                    throw new RuntimeException(sprintf('Invalid filter chain before for consumer=%s. Available values: %s', $consumerId, implode(', ', ['match_all', 'match_any'])));
                }

                $filterStrategyAfter = $targetAttrs['after'];
                if ('match_all' === $filterStrategyAfter) {
                    $inboundAfterFilters = $afterFilters + ($consumerAfterFilters[$consumerId] ?? []);
                    ksort($inboundAfterFilters);
                    $inboundAfterFilters = array_unique(array_values($inboundAfterFilters), SORT_REGULAR);

                    $filterAfter = (new Definition(MatchAllMessageFilters::class, [
                        new IteratorArgument($inboundAfterFilters),
                    ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
                } elseif ('match_any' === $filterStrategyAfter) {
                    $inboundAfterFilters = $afterFilters + ($consumerAfterFilters[$consumerId] ?? []);
                    ksort($inboundAfterFilters);
                    $inboundAfterFilters = array_unique(array_values($inboundAfterFilters), SORT_REGULAR);

                    $filterAfter = (new Definition(MatchAnyMessageFilter::class, [
                        new IteratorArgument($inboundAfterFilters),
                    ]))->setFactory([MatchAnyMessageFiltersFactory::class, 'create']);
                } else {
                    throw new RuntimeException(sprintf('Invalid filter strategy after for consumer=%s. Available values: %s', $consumerId, implode(', ', ['match_all', 'match_any'])));
                }
            }

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

    private function checkTarget(ContainerBuilder $container, string $targetId, string $id): void
    {
        if ($container->hasDefinition($targetId)) {
            return;
        }

        throw new RuntimeException(sprintf('Acl inbound target=%s for service=%s does not exists.', $targetId, $id));
    }
}
