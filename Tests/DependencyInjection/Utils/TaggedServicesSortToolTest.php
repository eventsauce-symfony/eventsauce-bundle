<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Utils;

use Andreo\EventSauceBundle\DependencyInjection\Utils\TaggedServicesSortTool;
use Monolog\Test\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class TaggedServicesSortToolTest extends TestCase
{
    /**
     * @test
     */
    public function should_asc_sort_tagged_services(): void
    {
        $container = new ContainerBuilder();
        $container
            ->setDefinition('service_1', new Definition())
            ->addTag('sort_asc_tag', ['version' => 4])
        ;
        $container
            ->setDefinition('service_2', new Definition())
            ->addTag('sort_asc_tag', ['version' => 3])
        ;
        $container
            ->setDefinition('service_4', new Definition())
            ->addTag('sort_asc_tag', ['version' => 1])
        ;
        $container
            ->setDefinition('service_3', new Definition())
            ->addTag('sort_asc_tag', ['version' => 2])
        ;

        $services = TaggedServicesSortTool::findAndSort($container, 'sort_asc_tag', 'version', true);

        $trueOrder = ['service_4', 'service_3', 'service_2', 'service_1'];

        foreach ($services as $reference) {
            $trueOrderRef = array_shift($trueOrder);
            $this->assertSame($reference->__toString(), $trueOrderRef);
        }
    }

    /**
     * @test
     */
    public function should_desc_sort_tagged_services(): void
    {
        $container = new ContainerBuilder();
        $container
            ->setDefinition('service_1', new Definition())
            ->addTag('sort_desc_tag', ['priority' => 4])
        ;
        $container
            ->setDefinition('service_3', new Definition())
            ->addTag('sort_desc_tag', ['priority' => 2])
        ;
        $container
            ->setDefinition('service_2', new Definition())
            ->addTag('sort_desc_tag', ['priority' => 3])
        ;
        $container
            ->setDefinition('service_4', new Definition())
            ->addTag('sort_desc_tag', ['priority' => 1])
        ;

        $services = TaggedServicesSortTool::findAndSort($container, 'sort_desc_tag');

        $trueOrder = ['service_1', 'service_2', 'service_3', 'service_4'];

        foreach ($services as $reference) {
            $trueOrderRef = array_shift($trueOrder);
            $this->assertSame($reference->__toString(), $trueOrderRef);
        }
    }
}
