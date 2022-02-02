<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

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
    public function upcast_config_is_loading(): void
    {
        $this->load([
            'upcast' => [
                'enabled' => true,
            ],
        ]);

        $this->assertArrayHasKey(AsUpcaster::class, $this->container->getAutoconfiguredAttributes());
    }
}
