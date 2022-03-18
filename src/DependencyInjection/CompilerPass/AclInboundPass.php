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

    public function process(ContainerBuilder $container): void
    {
        $translators = [];
        $consumerTranslators = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_inbound.message_translator', $container) as $index => $reference) {
            $translatorDef = $container->findDefinition($reference->__toString());
            if (!$translatorDef->hasTag('andreo.eventsauce.acl_inbound_target')) {
                $translators[$index] = $reference;
            } else {
                [$targetAttrs] = $translatorDef->getTag('andreo.eventsauce.acl_inbound_target');
                $targetId = $targetAttrs['id'];
                $this->checkTarget($container, $targetId, $reference->__toString());
                $consumerTranslators[$targetId][$index] = $reference;
            }
        }

        $beforeFilters = [];
        $consumerBeforeFilters = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_inbound.filter_before', $container) as $index => $reference) {
            $translatorDef = $container->findDefinition($reference->__toString());
            if (!$translatorDef->hasTag('andreo.eventsauce.acl_inbound_target')) {
                $beforeFilters[$index] = $reference;
            } else {
                [$targetAttrs] = $translatorDef->getTag('andreo.eventsauce.acl_inbound_target');
                $targetId = $targetAttrs['id'];
                $this->checkTarget($container, $targetId, $reference->__toString());
                $consumerBeforeFilters[$targetId][$index] = $reference;
            }
        }

        $afterFilters = [];
        $consumerAfterFilters = [];
        foreach ($this->findAndSortTaggedServices('andreo.eventsauce.acl_inbound.filter_after', $container) as $index => $reference) {
            $translatorDef = $container->findDefinition($reference->__toString());
            if (!$translatorDef->hasTag('andreo.eventsauce.acl_inbound_target')) {
                $afterFilters[$index] = $reference;
            } else {
                [$targetAttrs] = $translatorDef->getTag('andreo.eventsauce.acl_inbound_target');
                $targetId = $targetAttrs['id'];
                $this->checkTarget($container, $targetId, $reference->__toString());
                $consumerAfterFilters[$targetId][$index] = $reference;
            }
        }

        $allowAllDef = new Definition(AllowAllMessages::class);
        $filterBefore = $allowAllDef;
        $filterAfter = $allowAllDef;

        foreach ($container->findTaggedServiceIds('andreo.eventsauce.acl_inbound') as $consumerId => [$attrs]) {
            $inboundTranslators = $translators + ($consumerTranslators[$consumerId] ?? []);
            ksort($inboundTranslators);
            $inboundTranslators = array_values($inboundTranslators);

            $messageTranslatorChain = (new Definition(MessageTranslatorChain::class, [
                new IteratorArgument($inboundTranslators),
            ]))->setFactory([MessageTranslatorChainFactory::class, 'create']);

            $consumerDef = $container->findDefinition($consumerId);
            if ($consumerDef->hasTag('andreo.eventsauce.acl.filter_chain')) {
                [$targetAttrs] = $consumerDef->getTag('andreo.eventsauce.acl.filter_chain');

                $filterChainBefore = $targetAttrs['before'];
                if ('match_all' === $filterChainBefore) {
                    $inboundBeforeFilters = $beforeFilters + ($consumerBeforeFilters[$consumerId] ?? []);
                    ksort($inboundBeforeFilters);
                    $inboundBeforeFilters = array_values($inboundBeforeFilters);

                    $filterBefore = (new Definition(MatchAllMessageFilters::class, [
                        new IteratorArgument($inboundBeforeFilters),
                    ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
                } elseif ('match_any' === $filterChainBefore) {
                    $inboundBeforeFilters = $beforeFilters + ($consumerBeforeFilters[$consumerId] ?? []);
                    ksort($inboundBeforeFilters);
                    $inboundBeforeFilters = array_values($inboundBeforeFilters);

                    $filterBefore = (new Definition(MatchAnyMessageFilter::class, [
                        new IteratorArgument($inboundBeforeFilters),
                    ]))->setFactory([MatchAnyMessageFiltersFactory::class, 'create']);
                } else {
                    throw new RuntimeException(sprintf('Invalid filter chain before for consumer=%s. Available values: %s', $consumerId, implode(', ', ['match_all', 'match_any'])));
                }

                $filterChainAfter = $targetAttrs['after'];
                if ('match_all' === $filterChainAfter) {
                    $inboundAfterFilters = $afterFilters + ($consumerAfterFilters[$consumerId] ?? []);
                    ksort($inboundAfterFilters);
                    $inboundAfterFilters = array_values($inboundAfterFilters);

                    $filterAfter = (new Definition(MatchAllMessageFilters::class, [
                        new IteratorArgument($inboundAfterFilters),
                    ]))->setFactory([MatchAllMessageFiltersFactory::class, 'create']);
                } elseif ('match_any' === $filterChainAfter) {
                    $inboundAfterFilters = $afterFilters + ($consumerAfterFilters[$consumerId] ?? []);
                    ksort($inboundAfterFilters);
                    $inboundAfterFilters = array_values($inboundAfterFilters);

                    $filterAfter = (new Definition(MatchAnyMessageFilter::class, [
                        new IteratorArgument($inboundAfterFilters),
                    ]))->setFactory([MatchAnyMessageFiltersFactory::class, 'create']);
                } else {
                    throw new RuntimeException(sprintf('Invalid filter chain after for consumer=%s. Available values: %s', $consumerId, implode(', ', ['match_all', 'match_any'])));
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
