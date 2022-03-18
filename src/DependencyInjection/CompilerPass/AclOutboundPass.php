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

    public function process(ContainerBuilder $container): void
    {
        $translators = [];
        $dispatcherTranslators = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_outbound.message_translator', $container) as $index => $reference) {
            $translatorDef = $container->findDefinition($reference->__toString());
            if (!$translatorDef->hasTag('andreo.eventsauce.acl_outbound_target')) {
                $translators[$index] = $reference;
            } else {
                [$targetAttrs] = $translatorDef->getTag('andreo.eventsauce.acl_outbound_target');
                $targetId = $targetAttrs['id'];
                $this->checkTarget($container, $targetId, $reference->__toString());
                $dispatcherTranslators[$targetId][$index] = $reference;
            }
        }

        $beforeFilters = [];
        $dispatcherBeforeFilters = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_outbound.filter_before', $container) as $index => $reference) {
            $translatorDef = $container->findDefinition($reference->__toString());
            if (!$translatorDef->hasTag('andreo.eventsauce.acl_outbound_target')) {
                $beforeFilters[$index] = $reference;
            } else {
                [$targetAttrs] = $translatorDef->getTag('andreo.eventsauce.acl_outbound_target');
                $targetId = $targetAttrs['id'];

                $this->checkTarget($container, $targetId, $reference->__toString());
                $dispatcherBeforeFilters[$targetId][$index] = $reference;
            }
        }

        $afterFilters = [];
        $dispatcherAfterFilters = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_outbound.filter_after', $container) as $index => $reference) {
            $translatorDef = $container->findDefinition($reference->__toString());
            if (!$translatorDef->hasTag('andreo.eventsauce.acl_outbound_target')) {
                $afterFilters[$index] = $reference;
            } else {
                [$targetAttrs] = $translatorDef->getTag('andreo.eventsauce.acl_outbound_target');
                $targetId = $targetAttrs['id'];
                $this->checkTarget($container, $targetId, $reference->__toString());
                $dispatcherAfterFilters[$targetId][$index] = $reference;
            }
        }

        $allowAllDef = new Definition(AllowAllMessages::class);
        $filterBefore = $allowAllDef;
        $filterAfter = $allowAllDef;

        foreach ($container->findTaggedServiceIds('andreo.eventsauce.acl_outbound') as $dispatcherId => [$attrs]) {
            $outboundTranslators = $translators + ($dispatcherTranslators[$dispatcherId] ?? []);
            ksort($outboundTranslators);
            $outboundTranslators = array_values($outboundTranslators);

            $messageTranslatorChain = (new Definition(MessageTranslatorChain::class, [
                new IteratorArgument($outboundTranslators),
            ]))->setFactory([MessageTranslatorChainFactory::class, 'create']);

            $dispatcherDef = $container->findDefinition($dispatcherId);
            if ($dispatcherDef->hasTag('andreo.eventsauce.acl.filter_strategy')) {
                [$targetAttrs] = $dispatcherDef->getTag('andreo.eventsauce.acl.filter_strategy');

                $filterChainBefore = $targetAttrs['before'];
                if ('match_all' === $filterChainBefore) {
                    $outboundBeforeFilters = $beforeFilters + ($dispatcherBeforeFilters[$dispatcherId] ?? []);
                    ksort($outboundBeforeFilters);
                    $outboundBeforeFilters = array_values($outboundBeforeFilters);

                    $filterBefore = (new Definition(MatchAllMessageFilters::class, [
                        new IteratorArgument($outboundBeforeFilters),
                    ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
                } elseif ('match_any' === $filterChainBefore) {
                    $outboundBeforeFilters = $beforeFilters + ($dispatcherBeforeFilters[$dispatcherId] ?? []);
                    ksort($outboundBeforeFilters);
                    $outboundBeforeFilters = array_values($outboundBeforeFilters);

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
                    $outboundAfterFilters = array_values($outboundAfterFilters);

                    $filterAfter = (new Definition(MatchAllMessageFilters::class, [
                        new IteratorArgument($outboundAfterFilters),
                    ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
                } elseif ('match_any' === $filterChainAfter) {
                    $outboundAfterFilters = $afterFilters + ($dispatcherAfterFilters[$dispatcherId] ?? []);
                    ksort($outboundAfterFilters);
                    $outboundAfterFilters = array_values($outboundAfterFilters);

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
