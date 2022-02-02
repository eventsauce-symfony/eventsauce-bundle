<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class UpcastConfigTest extends AbstractExtensionTestCase
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
    public function should_register_upcaster_autoconfiguration(): void
    {
        $this->load([
            'upcast' => [
                'enabled' => true,
            ],
        ]);

        $this->assertArrayHasKey(AsUpcaster::class, $this->container->getAutoconfiguredAttributes());
    }
}
