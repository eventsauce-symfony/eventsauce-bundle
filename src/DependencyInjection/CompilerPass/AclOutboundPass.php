<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\CompilerPass;

use Andreo\EventSauceBundle\Factory\MatchAllMessageFiltersFactory;
use Andreo\EventSauceBundle\Factory\MatchAnyMessageFiltersFactory;
use Andreo\EventSauceBundle\Factory\MessageTranslatorChainFactory;
use EventSauce\EventSourcing\AntiCorruptionLayer\AllowAllMessages;
use EventSauce\EventSourcing\AntiCorruptionLayer\AntiCorruptionMessageDispatcher;
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

final class AclOutboundPass implements CompilerPassInterface
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
        $dispatcherTranslators = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_outbound.message_translator', $container) as $index => $translatorReference) {
            $translatorDef = $container->findDefinition($translatorReference->__toString());
            if (!$translatorDef->hasTag('andreo.eventsauce.acl_outbound_target')) {
                $translators[$index] = $translatorReference;
            } else {
                [$targetAttrs] = $translatorDef->getTag('andreo.eventsauce.acl_outbound_target');
                $translatorTargetId = $targetAttrs['id'] ?? null;
                if (null === $translatorTargetId) {
                    $translators[$index] = $translatorReference;
                    continue;
                }
                $this->checkTarget($container, $translatorTargetId, $translatorReference->__toString());
                $dispatcherTranslators[$translatorTargetId][$index] = $translatorReference;
            }
        }

        $beforeFilters = [];
        $dispatcherBeforeFilters = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_outbound.filter_before', $container) as $index => $filterBeforeReference) {
            $filterBeforeDef = $container->findDefinition($filterBeforeReference->__toString());
            if (!$filterBeforeDef->hasTag('andreo.eventsauce.acl_outbound_target')) {
                $beforeFilters[$index] = $filterBeforeReference;
            } else {
                [$targetAttrs] = $filterBeforeDef->getTag('andreo.eventsauce.acl_outbound_target');
                $filterBeforeTargetId = $targetAttrs['id'] ?? null;
                if (null === $filterBeforeTargetId) {
                    $beforeFilters[$index] = $filterBeforeReference;
                    continue;
                }
                $this->checkTarget($container, $filterBeforeTargetId, $filterBeforeReference->__toString());
                $dispatcherBeforeFilters[$filterBeforeTargetId][$index] = $filterBeforeReference;
            }
        }

        $afterFilters = [];
        $dispatcherAfterFilters = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_outbound.filter_after', $container) as $index => $reference) {
            $filterAfterDef = $container->findDefinition($reference->__toString());
            if (!$filterAfterDef->hasTag('andreo.eventsauce.acl_outbound_target')) {
                $afterFilters[$index] = $reference;
            } else {
                [$targetAttrs] = $filterAfterDef->getTag('andreo.eventsauce.acl_outbound_target');
                $filterAfterTargetId = $targetAttrs['id'] ?? null;
                if (null === $filterAfterTargetId) {
                    $afterFilters[$index] = $reference;
                    continue;
                }
                $this->checkTarget($container, $filterAfterTargetId, $reference->__toString());
                $dispatcherAfterFilters[$filterAfterTargetId][$index] = $reference;
            }
        }

        $allowAllDef = new Definition(AllowAllMessages::class);
        $filterBefore = $allowAllDef;
        $filterAfter = $allowAllDef;

        foreach ($container->findTaggedServiceIds('andreo.eventsauce.acl_outbound') as $dispatcherId => [$attrs]) {
            $outboundTranslators = $translators + ($dispatcherTranslators[$dispatcherId] ?? []);
            ksort($outboundTranslators);
            $outboundTranslators = array_unique(array_values($outboundTranslators), SORT_REGULAR);

            $messageTranslatorChain = (new Definition(MessageTranslatorChain::class, [
                new IteratorArgument($outboundTranslators),
            ]))->setFactory([MessageTranslatorChainFactory::class, 'create']);

            $dispatcherDef = $container->findDefinition($dispatcherId);
            if ($dispatcherDef->hasTag('andreo.eventsauce.acl.filter_chain')) {
                [$targetAttrs] = $dispatcherDef->getTag('andreo.eventsauce.acl.filter_chain');

                $filterChainBefore = $targetAttrs['before'];
                if ('match_all' === $filterChainBefore) {
                    $outboundBeforeFilters = $beforeFilters + ($dispatcherBeforeFilters[$dispatcherId] ?? []);
                    ksort($outboundBeforeFilters);
                    $outboundBeforeFilters = array_unique(array_values($outboundBeforeFilters), SORT_REGULAR);

                    $filterBefore = (new Definition(MatchAllMessageFilters::class, [
                        new IteratorArgument($outboundBeforeFilters),
                    ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
                } elseif ('match_any' === $filterChainBefore) {
                    $outboundBeforeFilters = $beforeFilters + ($dispatcherBeforeFilters[$dispatcherId] ?? []);
                    ksort($outboundBeforeFilters);
                    $outboundBeforeFilters = array_unique(array_values($outboundBeforeFilters), SORT_REGULAR);

                    $filterBefore = (new Definition(MatchAnyMessageFilter::class, [
                        new IteratorArgument($outboundBeforeFilters),
                    ]))->setFactory([MatchAnyMessageFiltersFactory::class, 'create']);
                } else {
                    throw new RuntimeException(sprintf('Invalid filter chain before for dispatcher=%s. Available values: %s', $dispatcherId, implode(', ', ['match_all', 'match_any'])));
                }

                $filterChainAfter = $targetAttrs['after'];
                if ('match_all' === $filterChainAfter) {
                    $outboundAfterFilters = $afterFilters + ($dispatcherAfterFilters[$dispatcherId] ?? []);
                    ksort($outboundAfterFilters);
                    $outboundAfterFilters = array_unique(array_values($outboundAfterFilters), SORT_REGULAR);

                    $filterAfter = (new Definition(MatchAllMessageFilters::class, [
                        new IteratorArgument($outboundAfterFilters),
                    ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
                } elseif ('match_any' === $filterChainAfter) {
                    $outboundAfterFilters = $afterFilters + ($dispatcherAfterFilters[$dispatcherId] ?? []);
                    ksort($outboundAfterFilters);
                    $outboundAfterFilters = array_unique(array_values($outboundAfterFilters), SORT_REGULAR);

                    $filterAfter = (new Definition(MatchAnyMessageFilter::class, [
                        new IteratorArgument($outboundAfterFilters),
                    ]))->setFactory([MatchAnyMessageFiltersFactory::class, 'create']);
                } else {
                    throw new RuntimeException(sprintf('Invalid filter chain after for dispatcher=%s. Available values: %s', $dispatcherId, implode(', ', ['match_all', 'match_any'])));
                }
            }

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
    }

    private function checkTarget(ContainerBuilder $container, string $targetId, string $id): void
    {
        if ($container->hasDefinition($targetId)) {
            return;
        }

        throw new RuntimeException(sprintf('Acl outbound target=%s for service=%s does not exists.', $targetId, $id));
    }
}
