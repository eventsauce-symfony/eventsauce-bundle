<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Doctrine\Migration\GenerateAggregateMigrationCommand;
use Andreo\EventSauce\Doctrine\Migration\TableNameSuffix;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MigrationGeneratorLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private YamlFileLoader $loader, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $migrationGeneratorConfig = $config['migration_generator'];
        if (!$this->extension->isConfigEnabled($this->container, $migrationGeneratorConfig)) {
            return;
        }

        if (!class_exists(GenerateAggregateMigrationCommand::class)) {
            throw new LogicException('Migration generator is not available. Try running "composer require andreo/eventsauce-migration-generator".');
        }

        $dependencyFactoryId = $migrationGeneratorConfig['dependency_factory'];
        $this->container->setAlias('andreo.eventsauce.migration_generator.dependency_factory', $dependencyFactoryId);

        $messageDoctrineConfig = $config['event_store']['repository']['doctrine'];
        $eventTableName = $messageDoctrineConfig['table_name'];

        $snapshotDoctrineConfig = $config['snapshot']['repository']['doctrine'];
        $snapshotTableName = $snapshotDoctrineConfig['table_name'];

        $outboxDoctrineConfig = $config['outbox']['repository']['doctrine'];
        $outboxTableName = $outboxDoctrineConfig['table_name'];

        $this->container
            ->register(TableNameSuffix::class, TableNameSuffix::class)
            ->setArguments([
                $eventTableName,
                $outboxTableName,
                $snapshotTableName,
            ])
            ->setPublic(false)
        ;

        $this->loader->load('migration.yaml');
    }
}
