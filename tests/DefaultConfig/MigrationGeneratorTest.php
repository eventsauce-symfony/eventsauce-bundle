<?php

declare(strict_types=1);

namespace Tests\DefaultConfig;

use Andreo\EventSauce\Doctrine\Migration\TableNameSuffix;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Doctrine\Migrations\DependencyFactory;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class MigrationGeneratorTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_migration_generator(): void
    {
        $this->load([
            'migration_generator' => [
                'dependency_factory' => 'dummy_dependency_factor_id',
            ],
        ]);

        $this->container->register('dummy_dependency_factor_id', DependencyFactory::class);
        $this->assertContainerBuilderHasAlias('andreo.eventsauce.migration_generator.dependency_factory');
        $alias = $this->container->getAlias('andreo.eventsauce.migration_generator.dependency_factory');
        $this->assertEquals('dummy_dependency_factor_id', $alias->__toString());

        $this->assertContainerBuilderHasService(TableNameSuffix::class);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
