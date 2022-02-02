<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauce\Snapshotting\ConstructingSnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Tests\ConfigExtension\Dummy\DummySnapshotStateSerializer;

final class SnapshotConfigTest extends AbstractExtensionTestCase
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
    public function snapshot_state_serializer_is_loading(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'repository' => [
                    'doctrine' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(SnapshotStateSerializer::class);
        $snapshotSerializerAlias = $this->container->getAlias(SnapshotStateSerializer::class);
        $this->assertEquals(ConstructingSnapshotStateSerializer::class, $snapshotSerializerAlias->__toString());
    }

    /**
     * @test
     */
    public function custom_snapshot_state_serializer_is_loading(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'repository' => [
                    'doctrine' => [
                        'enabled' => true,
                    ],
                ],
                'serializer' => DummySnapshotStateSerializer::class,
            ],
        ]);

        $this->assertContainerBuilderHasAlias(SnapshotStateSerializer::class);
        $snapshotSerializerAlias = $this->container->getAlias(SnapshotStateSerializer::class);
        $this->assertEquals(DummySnapshotStateSerializer::class, $snapshotSerializerAlias->__toString());
    }

    /**
     * @test
     */
    public function snapshot_state_serializer_is_not_loading(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'repository' => [
                    'memory' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderNotHasService(ConstructingSnapshotStateSerializer::class);
    }
}
