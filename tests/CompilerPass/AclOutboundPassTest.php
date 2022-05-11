<?php

declare(strict_types=1);

namespace Tests\CompilerPass;

use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclOutboundPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class AclOutboundPassTest extends AbstractCompilerPassTestCase
{
    /**
     * @test
     */
    public function should_register_inbound_acl_without_target_definitions(): void
    {
        $translatorDef = new Definition();
        $translatorDef->addTag('andreo.eventsauce.acl_outbound.translator');
        $this->setDefinition('foo_translator', $translatorDef);

        $filterBeforeDef = new Definition();
        $filterBeforeDef->addTag('andreo.eventsauce.acl_outbound.filter_before');
        $this->setDefinition('foo_filter_before', $filterBeforeDef);

        $filterAfterDef = new Definition();
        $filterAfterDef->addTag('andreo.eventsauce.acl_outbound.filter_after');
        $this->setDefinition('foo_filter_after', $filterAfterDef);

        $dispatcherDef = new Definition();
        $dispatcherDef->addTag('andreo.eventsauce.acl_outbound');
        $this->setDefinition('foo_dispatcher', $dispatcherDef);

        $this->compile();

        $this->assertContainerBuilderHasService(sprintf('%s.acl', 'foo_dispatcher'));
    }

    /**
     * @test
     */
    public function should_register_inbound_acl_with_translator_target_definitions(): void
    {
        $translatorDef = new Definition();
        $translatorDef
            ->addTag('andreo.eventsauce.acl_outbound.translator')
            ->addTag('andreo.eventsauce.acl_outbound_target');
        $this->setDefinition('foo_translator', $translatorDef);

        $translatorV2Def = new Definition();
        $translatorV2Def
            ->addTag('andreo.eventsauce.acl_outbound.translator')
            ->addTag('andreo.eventsauce.acl_outbound_target', [
                'id' => 'foo_dispatcher',
            ]);
        $this->setDefinition('foo_translator_v2', $translatorV2Def);

        $translatorV3Def = new Definition();
        $translatorV3Def
            ->addTag('andreo.eventsauce.acl_outbound.translator')
            ->addTag('andreo.eventsauce.acl_outbound_target', [
                'id' => 'other_dispatcher',
            ]);
        $this->setDefinition('foo_translator_v3', $translatorV3Def);

        $dispatcherDef = new Definition();
        $dispatcherDef->addTag('andreo.eventsauce.acl_outbound');
        $this->setDefinition('foo_dispatcher', $dispatcherDef);

        $otherDispatcherDef = new Definition();
        $otherDispatcherDef->addTag('andreo.eventsauce.acl_outbound');
        $this->setDefinition('other_dispatcher', $otherDispatcherDef);

        $this->compile();

        $this->assertContainerBuilderHasService($dispatcherId = sprintf('%s.acl', 'foo_dispatcher'));
        $dispatcherDef = $this->container->findDefinition($dispatcherId);

        /** @var IteratorArgument $translatorChainArguments */
        $translatorChainArguments = $dispatcherDef->getArgument(1)->getArgument(0);
        $this->assertCount(2, $translatorChainArguments->getValues());
    }

    /**
     * @test
     */
    public function should_register_inbound_acl_with_filter_before_target_definitions(): void
    {
        $filterBeforeDef = new Definition();
        $filterBeforeDef
            ->addTag('andreo.eventsauce.acl_outbound.filter_before')
            ->addTag('andreo.eventsauce.acl_outbound_target')
        ;
        $this->setDefinition('foo_filter_before', $filterBeforeDef);

        $filterBeforeV2Def = new Definition();
        $filterBeforeV2Def
            ->addTag('andreo.eventsauce.acl_outbound.filter_before')
            ->addTag('andreo.eventsauce.acl_outbound_target', [
                'id' => 'other_dispatcher',
            ])
        ;
        $this->setDefinition('foo_filter_before_v2', $filterBeforeV2Def);

        $filterBeforeV3Def = new Definition();
        $filterBeforeV3Def
            ->addTag('andreo.eventsauce.acl_outbound.filter_before')
            ->addTag('andreo.eventsauce.acl_outbound_target', [
                'id' => 'foo_dispatcher',
            ])
        ;
        $this->setDefinition('foo_filter_before_v3', $filterBeforeV3Def);

        $dispatcherDef = new Definition();
        $dispatcherDef
            ->addTag('andreo.eventsauce.acl_outbound')
            ->addTag('andreo.eventsauce.acl.filter_strategy', [
                'before' => 'match_all',
                'after' => 'match_all',
            ])
        ;
        $this->setDefinition('foo_dispatcher', $dispatcherDef);

        $otherDispatcherDef = new Definition();
        $otherDispatcherDef->addTag('andreo.eventsauce.acl_outbound');
        $this->setDefinition('other_dispatcher', $otherDispatcherDef);

        $this->compile();

        $this->assertContainerBuilderHasService($dispatcherId = sprintf('%s.acl', 'foo_dispatcher'));
        $dispatcherDef = $this->container->findDefinition($dispatcherId);

        /** @var IteratorArgument $translatorChainArguments */
        $translatorChainArguments = $dispatcherDef->getArgument(2)->getArgument(0);
        $this->assertCount(2, $translatorChainArguments->getValues());
    }

    /**
     * @test
     */
    public function should_register_inbound_acl_with_filter_after_target_definitions(): void
    {
        $filterAfterDef = new Definition();
        $filterAfterDef
            ->addTag('andreo.eventsauce.acl_outbound.filter_after')
            ->addTag('andreo.eventsauce.acl_outbound_target')
        ;
        $this->setDefinition('foo_filter_after', $filterAfterDef);

        $filterAfterV2Def = new Definition();
        $filterAfterV2Def
            ->addTag('andreo.eventsauce.acl_outbound.filter_after')
            ->addTag('andreo.eventsauce.acl_outbound_target', [
                'id' => 'other_dispatcher',
            ])
        ;
        $this->setDefinition('foo_filter_after_v2', $filterAfterV2Def);

        $filterAfterV3Def = new Definition();
        $filterAfterV3Def
            ->addTag('andreo.eventsauce.acl_outbound.filter_after')
            ->addTag('andreo.eventsauce.acl_outbound_target', [
                'id' => 'foo_dispatcher',
            ])
        ;
        $this->setDefinition('foo_filter_after_v3', $filterAfterV3Def);

        $dispatcherDef = new Definition();
        $dispatcherDef
            ->addTag('andreo.eventsauce.acl_outbound')
            ->addTag('andreo.eventsauce.acl.filter_strategy', [
                'before' => 'match_any',
                'after' => 'match_any',
            ])
        ;
        $this->setDefinition('foo_dispatcher', $dispatcherDef);

        $otherDispatcherDef = new Definition();
        $otherDispatcherDef->addTag('andreo.eventsauce.acl_outbound');
        $this->setDefinition('other_dispatcher', $otherDispatcherDef);

        $this->compile();

        $this->assertContainerBuilderHasService($dispatcherId = sprintf('%s.acl', 'foo_dispatcher'));
        $dispatcherDef = $this->container->findDefinition($dispatcherId);

        /** @var IteratorArgument $translatorChainArguments */
        $translatorChainArguments = $dispatcherDef->getArgument(3)->getArgument(0);
        $this->assertCount(2, $translatorChainArguments->getValues());
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AclOutboundPass());
    }
}
