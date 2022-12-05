<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauceBundle\Attribute\AsMessageFilter;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use Andreo\EventSauceBundle\Attribute\EnableAcl;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Enum\MessageFilterStrategy;
use Andreo\EventSauceBundle\Enum\MessageFilterTrigger;
use Andreo\EventSauceBundle\Tests\Doubles\DummyAclMessageConsumer;
use Andreo\EventSauceBundle\Tests\Doubles\DummyAclMessageDispatcher;
use Andreo\EventSauceBundle\Tests\Doubles\DummyMessageFilter;
use Andreo\EventSauceBundle\Tests\Doubles\DummyMessageTranslator;
use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\MessageDispatcher;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;

final class AclConfigTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_default_acl_config(): void
    {
        $this->load();

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayNotHasKey(EnableAcl::class, $attributes);
        $this->assertArrayNotHasKey(AsMessageFilter::class, $attributes);
        $this->assertArrayNotHasKey(AsMessageTranslator::class, $attributes);
        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_enabled', false);
    }

    /**
     * @test
     */
    public function should_load_consumer_acl_config(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(EnableAcl::class, $attributes);
        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_enabled', true);

        $this->container
            ->register(DummyAclMessageConsumer::class, DummyAclMessageConsumer::class)
            ->setAutoconfigured(true)
        ;
        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyAclMessageConsumer::class,
            'andreo.eventsauce.acl',
            [
                'message_filter_strategy_before_translate' => MessageFilterStrategy::MATCH_ANY->value,
                'message_filter_strategy_after_translate' => MessageFilterStrategy::MATCH_ALL->value,
            ]
        );
    }

    /**
     * @test
     */
    public function should_load_dispatcher_acl_config(): void
    {
        $this->load([
            'acl' => true,
        ]);
        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(EnableAcl::class, $attributes);
        $this->assertContainerBuilderHasParameter('andreo.eventsauce.acl_enabled', true);

        $this->container
            ->register(DummyAclMessageDispatcher::class, DummyAclMessageDispatcher::class)
            ->setAutoconfigured(true)
        ;
        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyAclMessageDispatcher::class,
            'andreo.eventsauce.acl',
            [
                'message_filter_strategy_before_translate' => MessageFilterStrategy::MATCH_ANY->value,
                'message_filter_strategy_after_translate' => MessageFilterStrategy::MATCH_ALL->value,
            ]
        );
    }

    /**
     * @test
     */
    public function should_load_message_filters_config(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $this->container
            ->register(DummyMessageFilter::class, DummyMessageFilter::class)
            ->setAutoconfigured(true)
        ;

        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyMessageFilter::class,
            'andreo.eventsauce.acl.message_filter',
            [
                'priority' => 10,
                'trigger' => MessageFilterTrigger::AFTER_TRANSLATE->value,
                'owners' => MessageConsumer::class,
            ]
        );
    }

    /**
     * @test
     */
    public function should_load_message_translators_config(): void
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
            'andreo.eventsauce.acl.message_translator',
            [
                'priority' => 0,
                'owners' => MessageDispatcher::class,
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
