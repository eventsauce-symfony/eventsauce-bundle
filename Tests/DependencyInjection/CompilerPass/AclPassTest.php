<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\CompilerPass;

use Andreo\EventSauceBundle\Attribute\AsMessageFilter;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclPass;
use Andreo\EventSauceBundle\Enum\MessageFilterStrategy;
use Andreo\EventSauceBundle\Enum\MessageFilterTrigger;
use Andreo\EventSauceBundle\Tests\Doubles\DummyAclMessageConsumer;
use Andreo\EventSauceBundle\Tests\Doubles\DummyAclMessageDispatcher;
use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\MessageDispatcher;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AclPassTest extends AbstractCompilerPassTestCase
{
    /**
     * @test
     */
    public function should_be_register_dispatcher_acl(): void
    {
        $this->setParameter('andreo.eventsauce.acl_enabled', true);

        $dummyAclMessageDispatcherDef = new Definition(DummyAclMessageDispatcher::class);
        $dummyAclMessageDispatcherDef->addTag('andreo.eventsauce.acl', [
            'message_filter_strategy_before_translate' => MessageFilterStrategy::MATCH_ANY->value,
            'message_filter_strategy_after_translate' => MessageFilterStrategy::MATCH_ALL->value,
        ]);
        $this->setDefinition(DummyAclMessageDispatcher::class, $dummyAclMessageDispatcherDef);

        $this->compile();

        $this->assertContainerBuilderHasService(sprintf('%s.acl', DummyAclMessageDispatcher::class));
    }

    /**
     * @test
     */
    public function should_be_register_dispatcher_acl_translators(): void
    {
        $this->setParameter('andreo.eventsauce.acl_enabled', true);

        $dummyAclMessageDispatcherDef = new Definition(DummyAclMessageDispatcher::class);
        $dummyAclMessageDispatcherDef->addTag('andreo.eventsauce.acl', [
            'message_filter_strategy_before_translate' => MessageFilterStrategy::MATCH_ANY->value,
            'message_filter_strategy_after_translate' => MessageFilterStrategy::MATCH_ALL->value,
        ]);
        $this->setDefinition('foo_dispatcher', $dummyAclMessageDispatcherDef);

        $fooMessageTranslator = new Definition(AsMessageTranslator::class);
        $fooMessageTranslator->addTag('andreo.eventsauce.acl.message_translator', [
            'priority' => 4,
            'owners' => MessageDispatcher::class,
        ]);
        $this->setDefinition('foo_acl_message_translator', $fooMessageTranslator);

        $barMessageTranslator = new Definition(AsMessageTranslator::class);
        $barMessageTranslator->addTag('andreo.eventsauce.acl.message_translator', [
            'priority' => 5,
            'owners' => ['foo_dispatcher'],
        ]);
        $this->setDefinition('bar_acl_message_translator', $barMessageTranslator);

        $this->compile();

        $dispatcherDef = $this->container->findDefinition(sprintf('%s.acl', 'foo_dispatcher'));

        /** @var Definition $translatorChainArgument */
        $translatorChainArgument = $dispatcherDef->getArgument(1);
        /** @var IteratorArgument $translatorChainIteratorArgument */
        $translatorChainIteratorArgument = $translatorChainArgument->getArgument(0);

        $translatorChainIteratorArgumentValues = $translatorChainIteratorArgument->getValues();
        $this->assertCount(2, $translatorChainIteratorArgumentValues);
        /** @var Reference $barTranslator */
        $barTranslator = reset($translatorChainIteratorArgumentValues);

        $this->assertSame('bar_acl_message_translator', $barTranslator->__toString());
    }

    /**
     * @test
     */
    public function should_be_register_dispatcher_acl_filters(): void
    {
        $this->setParameter('andreo.eventsauce.acl_enabled', true);

        $dummyAclMessageDispatcherDef = new Definition();
        $dummyAclMessageDispatcherDef->addTag('andreo.eventsauce.acl', [
            'message_filter_strategy_before_translate' => MessageFilterStrategy::MATCH_ANY->value,
            'message_filter_strategy_after_translate' => MessageFilterStrategy::MATCH_ALL->value,
        ]);
        $this->setDefinition(DummyAclMessageDispatcher::class, $dummyAclMessageDispatcherDef);

        $fooBeforeMessageFilter = new Definition(AsMessageFilter::class);
        $fooBeforeMessageFilter->addTag('andreo.eventsauce.acl.message_filter', [
            'priority' => 4,
            'owners' => MessageDispatcher::class,
            'trigger' => MessageFilterTrigger::BEFORE_TRANSLATE->value,
        ]);
        $this->setDefinition('foo_acl_before_message_filter', $fooBeforeMessageFilter);

        $barBeforeMessageFilter = new Definition(AsMessageFilter::class);
        $barBeforeMessageFilter->addTag('andreo.eventsauce.acl.message_filter', [
            'priority' => 5,
            'owners' => [DummyAclMessageDispatcher::class],
            'trigger' => MessageFilterTrigger::BEFORE_TRANSLATE->value,
        ]);
        $this->setDefinition('bar_acl_before_message_filter', $barBeforeMessageFilter);

        $fooAfterMessageFilter = new Definition(AsMessageFilter::class);
        $fooAfterMessageFilter->addTag('andreo.eventsauce.acl.message_filter', [
            'priority' => 10,
            'owners' => DummyAclMessageDispatcher::class,
            'trigger' => MessageFilterTrigger::AFTER_TRANSLATE->value,
        ]);
        $this->setDefinition('foo_acl_after_message_filter', $fooAfterMessageFilter);

        $barAfterMessageFilter = new Definition(AsMessageFilter::class);
        $barAfterMessageFilter->addTag('andreo.eventsauce.acl.message_filter', [
            'priority' => -1,
            'owners' => MessageDispatcher::class,
            'trigger' => MessageFilterTrigger::AFTER_TRANSLATE->value,
        ]);
        $this->setDefinition('bar_acl_after_message_filter', $barAfterMessageFilter);

        $this->compile();

        $dispatcherDef = $this->container->findDefinition(sprintf('%s.acl', DummyAclMessageDispatcher::class));

        /** @var Definition $matchAnyMessageFilterArgument */
        $matchAnyMessageFilterArgument = $dispatcherDef->getArgument(2);
        /** @var IteratorArgument $matchAnyMessageFilterIteratorArgument */
        $matchAnyMessageFilterIteratorArgument = $matchAnyMessageFilterArgument->getArgument(0);
        $matchAnyMessageFilterIteratorArgumentValues = $matchAnyMessageFilterIteratorArgument->getValues();
        $this->assertCount(2, $matchAnyMessageFilterIteratorArgumentValues);
        /** @var Reference $barBeforeMessageFilter */
        $barBeforeMessageFilter = reset($matchAnyMessageFilterIteratorArgumentValues);
        $this->assertSame('bar_acl_before_message_filter', $barBeforeMessageFilter->__toString());

        /** @var Definition $matchAllMessageFilterArgument */
        $matchAllMessageFilterArgument = $dispatcherDef->getArgument(3);
        /** @var IteratorArgument $matchAllMessageFilterIteratorArgument */
        $matchAllMessageFilterIteratorArgument = $matchAllMessageFilterArgument->getArgument(0);
        $matchAllMessageFilterIteratorArgumentValues = $matchAllMessageFilterIteratorArgument->getValues();
        $this->assertCount(2, $matchAllMessageFilterIteratorArgumentValues);
        /** @var Reference $fooAfterMessageFilter */
        $fooAfterMessageFilter = reset($matchAllMessageFilterIteratorArgumentValues);
        $this->assertSame('foo_acl_after_message_filter', $fooAfterMessageFilter->__toString());
    }

    /**
     * @test
     */
    public function should_be_register_consumer_acl(): void
    {
        $this->setParameter('andreo.eventsauce.acl_enabled', true);

        $dummyAclMessageConsumerDef = new Definition(DummyAclMessageConsumer::class);
        $dummyAclMessageConsumerDef->addTag('andreo.eventsauce.acl', [
            'message_filter_strategy_before_translate' => MessageFilterStrategy::MATCH_ANY->value,
            'message_filter_strategy_after_translate' => MessageFilterStrategy::MATCH_ALL->value,
        ]);
        $this->setDefinition(DummyAclMessageConsumer::class, $dummyAclMessageConsumerDef);

        $this->compile();

        $this->assertContainerBuilderHasService(sprintf('%s.acl', DummyAclMessageConsumer::class));
    }

    /**
     * @test
     */
    public function should_be_register_consumer_acl_translators(): void
    {
        $this->setParameter('andreo.eventsauce.acl_enabled', true);

        $dummyAclMessageConsumerDef = new Definition();
        $dummyAclMessageConsumerDef->addTag('andreo.eventsauce.acl', [
            'message_filter_strategy_before_translate' => MessageFilterStrategy::MATCH_ANY->value,
            'message_filter_strategy_after_translate' => MessageFilterStrategy::MATCH_ALL->value,
        ]);
        $this->setDefinition(DummyAclMessageConsumer::class, $dummyAclMessageConsumerDef);

        $fooMessageTranslator = new Definition(AsMessageTranslator::class);
        $fooMessageTranslator->addTag('andreo.eventsauce.acl.message_translator', [
            'priority' => 10,
            'owners' => MessageConsumer::class,
        ]);
        $this->setDefinition('foo_acl_message_translator', $fooMessageTranslator);

        $barMessageTranslator = new Definition(AsMessageTranslator::class);
        $barMessageTranslator->addTag('andreo.eventsauce.acl.message_translator', [
            'priority' => 5,
            'owners' => [DummyAclMessageConsumer::class],
        ]);
        $this->setDefinition('bar_acl_message_translator', $barMessageTranslator);

        $this->compile();

        $consumerDef = $this->container->findDefinition(sprintf('%s.acl', DummyAclMessageConsumer::class));

        /** @var Definition $translatorChainArgument */
        $translatorChainArgument = $consumerDef->getArgument(1);
        /** @var IteratorArgument $translatorChainIteratorArgument */
        $translatorChainIteratorArgument = $translatorChainArgument->getArgument(0);
        $translatorChainIteratorArgumentValues = $translatorChainIteratorArgument->getValues();
        $this->assertCount(2, $translatorChainIteratorArgumentValues);
        /** @var Reference $shouldBeBarTranslator */
        $shouldBeBarTranslator = reset($translatorChainIteratorArgumentValues);
        $this->assertSame('foo_acl_message_translator', $shouldBeBarTranslator->__toString());
    }

    /**
     * @test
     */
    public function should_be_register_consumer_acl_filters(): void
    {
        $this->setParameter('andreo.eventsauce.acl_enabled', true);

        $dummyAclMessageConsumerDef = new Definition(DummyAclMessageConsumer::class);
        $dummyAclMessageConsumerDef->addTag('andreo.eventsauce.acl', [
            'message_filter_strategy_before_translate' => MessageFilterStrategy::MATCH_ANY->value,
            'message_filter_strategy_after_translate' => MessageFilterStrategy::MATCH_ALL->value,
        ]);
        $this->setDefinition('foo_consumer', $dummyAclMessageConsumerDef);

        $fooBeforeMessageFilter = new Definition(AsMessageFilter::class);
        $fooBeforeMessageFilter->addTag('andreo.eventsauce.acl.message_filter', [
            'priority' => 4,
            'owners' => MessageConsumer::class,
            'trigger' => MessageFilterTrigger::BEFORE_TRANSLATE->value,
        ]);
        $this->setDefinition('foo_acl_before_message_filter', $fooBeforeMessageFilter);

        $barBeforeMessageFilter = new Definition(AsMessageFilter::class);
        $barBeforeMessageFilter->addTag('andreo.eventsauce.acl.message_filter', [
            'priority' => 5,
            'owners' => 'foo_consumer',
            'trigger' => MessageFilterTrigger::BEFORE_TRANSLATE->value,
        ]);
        $this->setDefinition('bar_acl_before_message_filter', $barBeforeMessageFilter);

        $fooAfterMessageFilter = new Definition(AsMessageFilter::class);
        $fooAfterMessageFilter->addTag('andreo.eventsauce.acl.message_filter', [
            'priority' => 10,
            'owners' => 'foo_consumer',
            'trigger' => MessageFilterTrigger::AFTER_TRANSLATE->value,
        ]);
        $this->setDefinition('foo_acl_after_message_filter', $fooAfterMessageFilter);

        $barAfterMessageFilter = new Definition(AsMessageFilter::class);
        $barAfterMessageFilter->addTag('andreo.eventsauce.acl.message_filter', [
            'priority' => -1,
            'owners' => MessageConsumer::class,
            'trigger' => MessageFilterTrigger::AFTER_TRANSLATE->value,
        ]);
        $this->setDefinition('bar_acl_after_message_filter', $barAfterMessageFilter);

        $this->compile();

        $consumerDef = $this->container->findDefinition(sprintf('%s.acl', 'foo_consumer'));

        /** @var Definition $matchAnyMessageFilterArgument */
        $matchAnyMessageFilterArgument = $consumerDef->getArgument(2);
        /** @var IteratorArgument $matchAnyMessageFilterIteratorArgument */
        $matchAnyMessageFilterIteratorArgument = $matchAnyMessageFilterArgument->getArgument(0);
        $this->assertCount(2, $matchAnyMessageFilterIteratorArgument->getValues());
        /** @var Reference $barBeforeMessageFilter */
        $barBeforeMessageFilter = $matchAnyMessageFilterIteratorArgument->getValues()[0] ?? new Reference('null');
        $this->assertSame('bar_acl_before_message_filter', $barBeforeMessageFilter->__toString());

        /** @var Definition $matchAllMessageFilterArgument */
        $matchAllMessageFilterArgument = $consumerDef->getArgument(3);
        /** @var IteratorArgument $matchAllMessageFilterIteratorArgument */
        $matchAllMessageFilterIteratorArgument = $matchAllMessageFilterArgument->getArgument(0);
        $matchAllMessageFilterIteratorArgumentValues = $matchAllMessageFilterIteratorArgument->getValues();
        $this->assertCount(2, $matchAllMessageFilterIteratorArgumentValues);
        /** @var Reference $fooAfterMessageFilter */
        $fooAfterMessageFilter = reset($matchAllMessageFilterIteratorArgumentValues);
        $this->assertSame('foo_acl_after_message_filter', $fooAfterMessageFilter->__toString());
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AclPass());
    }
}
