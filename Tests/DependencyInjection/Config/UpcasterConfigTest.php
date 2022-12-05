<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Tests\Doubles\DummyMessageUpcaster;
use Andreo\EventSauceBundle\Tests\Doubles\FooDummyAggregate;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class UpcasterConfigTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }

    /**
     * @test
     */
    public function should_load_upcaster_config(): void
    {
        $this->load([
            'upcaster' => true,
        ]);

        $this->container
            ->register(DummyMessageUpcaster::class, DummyMessageUpcaster::class)
            ->setAutoconfigured(true)
        ;
        $this->compile();

        $this->assertArrayHasKey(AsUpcaster::class, $this->container->getAutoconfiguredAttributes());

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            DummyMessageUpcaster::class,
            'andreo.eventsauce.upcaster',
            [
                'version' => 2,
                'class' => FooDummyAggregate::class,
            ]
        );
    }
}
