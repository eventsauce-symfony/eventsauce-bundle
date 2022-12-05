<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\CompilerPass;

use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\SyncMessageConsumerPass;
use Andreo\EventSauceBundle\Tests\Doubles\DummyMessageConsumer;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class SyncMessageConsumerPassTest extends AbstractCompilerPassTestCase
{
    /**
     * @test
     */
    public function should_be_add_consumers_to_dispatcher(): void
    {
        $dummyMessageConsumer1 = new Definition(DummyMessageConsumer::class);
        $dummyMessageConsumer1->addTag('andreo.eventsauce.sync_message_consumer', [
            'dispatcher' => 'foo_dispatcher',
        ]);
        $this->setDefinition('dummy_message_consumer_1', $dummyMessageConsumer1);

        $dummyMessageConsumer2 = new Definition(DummyMessageConsumer::class);
        $dummyMessageConsumer2->addTag('andreo.eventsauce.sync_message_consumer', [
            'dispatcher' => 'foo_dispatcher',
        ]);
        $this->setDefinition('dummy_message_consumer_2', $dummyMessageConsumer2);

        $this->container
            ->setDefinition('foo_dispatcher', new Definition())
        ;

        $dummyMessageConsumer3 = new Definition(DummyMessageConsumer::class);
        $dummyMessageConsumer3->addTag('andreo.eventsauce.sync_message_consumer', [
            'dispatcher' => 'bar_dispatcher',
        ]);
        $this->setDefinition('dummy_message_consumer_3', $dummyMessageConsumer3);

        $dummyMessageConsumer4 = new Definition(DummyMessageConsumer::class);
        $dummyMessageConsumer4->addTag('andreo.eventsauce.sync_message_consumer', [
            'dispatcher' => 'bar_dispatcher',
        ]);
        $this->setDefinition('dummy_message_consumer_4', $dummyMessageConsumer4);

        $this->container
            ->setDefinition('bar_dispatcher', new Definition())
        ;

        $this->compile();

        $fooDispatcherDef = $this->container->getDefinition('foo_dispatcher');
        /** @var IteratorArgument $iteratorArgumentOfUpcasters */
        $iteratorArgumentOfFooUpcasters = $fooDispatcherDef->getArgument(0);
        $consumerRefs = $iteratorArgumentOfFooUpcasters->getValues();
        $this->assertCount(2, $consumerRefs);

        $barDispatcherDef = $this->container->getDefinition('bar_dispatcher');
        /** @var IteratorArgument $iteratorArgumentOfUpcasters */
        $iteratorArgumentOfFooUpcasters = $barDispatcherDef->getArgument(0);
        $consumerRefs = $iteratorArgumentOfFooUpcasters->getValues();
        $this->assertCount(2, $consumerRefs);
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SyncMessageConsumerPass());
    }
}
