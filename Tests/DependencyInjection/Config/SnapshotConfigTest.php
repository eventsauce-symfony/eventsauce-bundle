<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauce\Snapshotting\Serializer\SnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\Versioned\SnapshotVersionComparator;
use Andreo\EventSauce\Snapshotting\Versioned\SnapshotVersionInflector;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class SnapshotConfigTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_default_snapshot_config(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(SnapshotStateSerializer::class);
        $this->assertContainerBuilderNotHasService(SnapshotVersionInflector::class);
        $this->assertContainerBuilderNotHasService(SnapshotVersionComparator::class);
    }

    /**
     * @test
     */
    public function should_load_extended_snapshot_config(): void
    {
        $this->load([
            'snapshot' => [
                'versioned' => true,
            ],
        ]);

        $this->assertContainerBuilderHasAlias(SnapshotStateSerializer::class);
        $this->assertContainerBuilderHasAlias(SnapshotVersionInflector::class);
        $this->assertContainerBuilderHasAlias(SnapshotVersionComparator::class);
    }

    /**
     * @test
     */
    public function should_not_load_extended_snapshot_config(): void
    {
        $this->load([
            'snapshot' => true,
        ]);

        $this->assertContainerBuilderNotHasService(SnapshotStateSerializer::class);
        $this->assertContainerBuilderNotHasService(SnapshotVersionInflector::class);
        $this->assertContainerBuilderNotHasService(SnapshotVersionComparator::class);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
