<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauceBundle\Attribute\WithInboundAcl;
use Andreo\EventSauceBundle\Attribute\WithOutboundAcl;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Tests\Dummy\DummyAclMessageConsumer;
use Tests\Dummy\DummyAclMessageDispatcher;
use Tests\Dummy\DummyMessageFilterAfter;
use Tests\Dummy\DummyMessageFilterBefore;
use Tests\Dummy\DummyMessageTranslator;

final class AclTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_inbound_acl(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $this->container
            ->register(DummyAclMessageConsumer::class, DummyAclMessageConsumer::class)
            ->setAutoconfigured(true)
        ;

        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(WithInboundAcl::class, $attributes);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyAclMessageConsumer::class,
            'andreo.eventsauce.acl_inbound'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyAclMessageConsumer::class,
            'andreo.eventsauce.acl.filter_strategy',
            [
                'before' => 'match_any',
                'after' => 'match_all',
            ]
        );
    }

    /**
     * @test
     */
    public function should_load_outbound_acl(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $this->container
            ->register(DummyAclMessageDispatcher::class, DummyAclMessageDispatcher::class)
            ->setAutoconfigured(true)
        ;

        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(WithOutboundAcl::class, $attributes);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyAclMessageDispatcher::class,
            'andreo.eventsauce.acl_outbound'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyAclMessageDispatcher::class,
            'andreo.eventsauce.acl.filter_strategy',
            [
                'before' => 'match_any',
                'after' => 'match_any',
            ]
        );
    }

    /**
     * @test
     */
    public function should_not_load_inbound_acl_if_only_outbound_acl_is_enabled(): void
    {
        $this->load([
            'acl' => [
                'outbound' => true,
            ],
        ]);

        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_outbound_enabled', true);
        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_inbound_enabled', false);
    }

    /**
     * @test
     */
    public function should_not_load_outbound_acl_if_only_inbound_acl_is_enabled(): void
    {
        $this->load([
            'acl' => [
                'inbound' => true,
            ],
        ]);

        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_inbound_enabled', true);
        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_outbound_enabled', false);
    }

    /**
     * @test
     */
    public function should_not_load_acl_id_it_is_disabled(): void
    {
        $this->load([
            'acl' => false,
        ]);

        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_inbound_enabled', false);
        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_outbound_enabled', false);
    }

    /**
     * @test
     */
    public function should_load_inbound_message_filters(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $this->container
            ->register(DummyMessageFilterAfter::class, DummyMessageFilterAfter::class)
            ->setAutoconfigured(true)
        ;
        $this->container
            ->register(DummyMessageFilterBefore::class, DummyMessageFilterBefore::class)
            ->setAutoconfigured(true)
        ;

        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyMessageFilterAfter::class,
            'andreo.eventsauce.acl_inbound.filter_after',
            [
                'priority' => 10,
            ]
        );

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyMessageFilterBefore::class,
            'andreo.eventsauce.acl_inbound.filter_before',
            [
                'priority' => 0,
            ]
        );
    }

    /**
     * @test
     */
    public function should_load_outbound_message_filters(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $this->container
            ->register(DummyMessageFilterAfter::class, DummyMessageFilterAfter::class)
            ->setAutoconfigured(true)
        ;
        $this->container
            ->register(DummyMessageFilterBefore::class, DummyMessageFilterBefore::class)
            ->setAutoconfigured(true)
        ;

        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyMessageFilterAfter::class,
            'andreo.eventsauce.acl_outbound.filter_after',
            [
                'priority' => 10,
            ]
        );

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyMessageFilterBefore::class,
            'andreo.eventsauce.acl_outbound.filter_before',
            [
                'priority' => 0,
            ]
        );
    }

    /**
     * @test
     */
    public function should_load_inbound_message_translators(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $this->container
            ->register(DummyMessageTranslator::class, DummyMessageTranslator::class)
            ->setAutoconfigured(true)
        ;
        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyMessageTranslator::class,
            'andreo.eventsauce.acl_inbound.translator',
            [
                'priority' => 0,
            ]
        );
    }

    /**
     * @test
     */
    public function should_load_outbound_message_translators(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $this->container
            ->register(DummyMessageTranslator::class, DummyMessageTranslator::class)
            ->setAutoconfigured(true)
        ;
        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyMessageTranslator::class,
            'andreo.eventsauce.acl_outbound.translator',
            [
                'priority' => 0,
            ]
        );
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
