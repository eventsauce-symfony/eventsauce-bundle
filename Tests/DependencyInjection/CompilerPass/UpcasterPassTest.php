<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\CompilerPass;

use Andreo\EventSauce\Upcasting\MessageUpcaster\MessageUpcasterChain;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\UpcasterPass;
use Andreo\EventSauceBundle\DependencyInjection\Utils\ReflectionTool;
use Andreo\EventSauceBundle\Factory\MessageUpcasterChainFactory;
use Andreo\EventSauceBundle\Factory\UpcasterChainFactory;
use Andreo\EventSauceBundle\Tests\Doubles\BarDummyAggregate;
use Andreo\EventSauceBundle\Tests\Doubles\DummyMessageUpcaster;
use Andreo\EventSauceBundle\Tests\Doubles\FooDummyAggregate;
use EventSauce\EventSourcing\Upcasting\UpcasterChain;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class UpcasterPassTest extends AbstractCompilerPassTestCase
{
    /**
     * @test
     */
    public function should_be_add_upcasters_to_chain(): void
    {
        $fooMessageUpcasterDef2 = new Definition(DummyMessageUpcaster::class);
        $fooMessageUpcasterDef2->addTag('andreo.eventsauce.upcaster', [
            'version' => 2,
            'class' => FooDummyAggregate::class,
        ]);
        $this->setDefinition('foo_message_upcaster_2', $fooMessageUpcasterDef2);

        $fooMessageUpcasterDef1 = new Definition(DummyMessageUpcaster::class);
        $fooMessageUpcasterDef1->addTag('andreo.eventsauce.upcaster', [
            'version' => 1,
            'class' => FooDummyAggregate::class,
        ]);
        $this->setDefinition('foo_message_upcaster_1', $fooMessageUpcasterDef1);

        $fooAggregateClassShortName = ReflectionTool::getLowerStringOfClassShortName(FooDummyAggregate::class);
        $this->container
            ->register("andreo.eventsauce.upcaster_chain.$fooAggregateClassShortName", UpcasterChain::class)
            ->setFactory([UpcasterChainFactory::class, 'create'])
        ;

        $barMessageUpcasterDef2 = new Definition(DummyMessageUpcaster::class);
        $barMessageUpcasterDef2->addTag('andreo.eventsauce.upcaster', [
            'version' => 2,
            'class' => BarDummyAggregate::class,
        ]);
        $this->setDefinition('bar_message_upcaster_2', $barMessageUpcasterDef2);

        $barMessageUpcasterDef5 = new Definition(DummyMessageUpcaster::class);
        $barMessageUpcasterDef5->addTag('andreo.eventsauce.upcaster', [
            'version' => 5,
            'class' => BarDummyAggregate::class,
        ]);
        $this->setDefinition('bar_message_upcaster_5', $barMessageUpcasterDef5);

        $barAggregateClassShortName = ReflectionTool::getLowerStringOfClassShortName(BarDummyAggregate::class);
        $this->container
            ->register("andreo.eventsauce.upcaster_chain.$barAggregateClassShortName", MessageUpcasterChain::class)
            ->setFactory([MessageUpcasterChainFactory::class, 'create'])
        ;

        $this->compile();

        $fooUpcasterChainDef = $this->container->getDefinition("andreo.eventsauce.upcaster_chain.$fooAggregateClassShortName");
        /** @var IteratorArgument $iteratorArgumentOfUpcasters */
        $iteratorArgumentOfFooUpcasters = $fooUpcasterChainDef->getArgument(0);
        $fooUpcastersRefs = $iteratorArgumentOfFooUpcasters->getValues();
        $fooUpcaster1 = reset($fooUpcastersRefs);
        $fooUpcaster2 = end($fooUpcastersRefs);

        $this->assertSame('foo_message_upcaster_1', $fooUpcaster1->__toString());
        $this->assertSame('foo_message_upcaster_2', $fooUpcaster2->__toString());

        $barUpcasterChainDef = $this->container->getDefinition("andreo.eventsauce.upcaster_chain.$barAggregateClassShortName");
        /** @var IteratorArgument $iteratorArgumentOfUpcasters */
        $iteratorArgumentOfBarUpcasters = $barUpcasterChainDef->getArgument(0);
        $barUpcastersRefs = $iteratorArgumentOfBarUpcasters->getValues();
        $barUpcaster2 = reset($barUpcastersRefs);
        $barUpcaster5 = end($barUpcastersRefs);

        $this->assertSame('bar_message_upcaster_2', $barUpcaster2->__toString());
        $this->assertSame('bar_message_upcaster_5', $barUpcaster5->__toString());
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new UpcasterPass());
    }
}
