<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauce\Doctrine\Migration\Command\GenerateDoctrineMigrationForEventSauceCommand;
use Andreo\EventSauce\Doctrine\Migration\Schema\TableNameSuffix;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class MigrationGeneratorTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_default_migration_generator_config(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(TableNameSuffix::class);
        $this->assertContainerBuilderNotHasService(GenerateDoctrineMigrationForEventSauceCommand::class);
    }

    /**
     * @test
     */
    public function should_load_migration_generator_config(): void
    {
        $this->load([
            'migration_generator' => [
                'dependency_factory' => 'dummy_dependency_factor_id',
            ],
        ]);

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
