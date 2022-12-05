<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Doctrine\Migration\Command\GenerateDoctrineMigrationForEventSauceCommand;
use Andreo\EventSauce\Doctrine\Migration\Schema\TableNameSuffix;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\FileLoader;

final readonly class MigrationGeneratorLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        FileLoader $loader,
        array $config
    ): void {
        $migrationGeneratorConfig = $config['migration_generator'];
        if (!$extension->isConfigEnabled($container, $migrationGeneratorConfig)) {
            return;
        }

        if (!class_exists(GenerateDoctrineMigrationForEventSauceCommand::class)) {
            throw new LogicException('Migration generator is not available. Try running "composer require andreo/eventsauce-migration-generator".');
        }

        $loader->load('migration-generator.php');

        $dependencyFactoryId = $migrationGeneratorConfig['dependency_factory'];
        $container->setAlias('andreo.eventsauce.migration_generator.dependency_factory', $dependencyFactoryId);

        $messageDoctrineConfig = $config['message_storage']['repository']['doctrine_3'];
        $eventTableName = $messageDoctrineConfig['table_name'];

        $snapshotDoctrineConfig = $config['snapshot']['repository']['doctrine'];
        $snapshotTableName = $snapshotDoctrineConfig['table_name'];

        $outboxDoctrineConfig = $config['message_outbox']['repository']['doctrine'];
        $outboxTableName = $outboxDoctrineConfig['table_name'];

        $container
            ->getDefinition(TableNameSuffix::class)
            ->setArguments([
                $eventTableName,
                $outboxTableName,
                $snapshotTableName,
            ])
        ;
    }
}
