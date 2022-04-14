<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Tests\Dummy\DummyMessageUpcaster;

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
    public function should_load_upcaster(): void
    {
        $this->load([
            'upcaster' => [
                'enabled' => true,
            ],
        ]);
        $this->container
            ->register(DummyMessageUpcaster::class, DummyMessageUpcaster::class)
            ->setAutoconfigured(true)
        ;
        $this->compile();

        $this->assertArrayHasKey(AsUpcaster::class, $this->container->getAutoconfiguredAttributes());

        $this->assertContainerBuilderHasServiceDefinitionWithTag(DummyMessageUpcaster::class, 'andreo.eventsauce.upcaster.dummy', [
            'priority' => -2,
        ]);
    }
}
