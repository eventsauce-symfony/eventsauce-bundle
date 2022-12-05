<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Andreo\EventSauce\Doctrine\Migration\Command\GenerateDoctrineMigrationForEventSauceCommand;
use Andreo\EventSauce\Doctrine\Migration\Schema\EventStoreSchemaBuilder;
use Andreo\EventSauce\Doctrine\Migration\Schema\MessageOutboxSchemaBuilder;
use Andreo\EventSauce\Doctrine\Migration\Schema\SnapshotStoreSchemaBuilder;
use Andreo\EventSauce\Doctrine\Migration\Schema\TableNameSuffix;
use Andreo\EventSauce\Snapshotting\Doctrine\Table\SnapshotTableSchema;
use EventSauce\MessageRepository\TableSchema\TableSchema;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->set(TableNameSuffix::class, TableNameSuffix::class)
        ->private()
    ;

    $services
        ->set(EventStoreSchemaBuilder::class, EventStoreSchemaBuilder::class)
        ->args([
            service(TableSchema::class),
        ])
        ->private()
    ;

    $services
        ->set(MessageOutboxSchemaBuilder::class, MessageOutboxSchemaBuilder::class)
        ->private()
    ;

    $services
        ->set(SnapshotStoreSchemaBuilder::class, SnapshotStoreSchemaBuilder::class)
        ->args([
            service(SnapshotTableSchema::class)->nullOnInvalid(),
        ])
        ->private()
    ;

    $services
        ->set(GenerateDoctrineMigrationForEventSauceCommand::class, GenerateDoctrineMigrationForEventSauceCommand::class)
        ->args([
            service('andreo.eventsauce.migration_generator.dependency_factory'),
            service(TableNameSuffix::class),
            service(EventStoreSchemaBuilder::class),
            service(MessageOutboxSchemaBuilder::class),
            service(SnapshotStoreSchemaBuilder::class),
        ])
        ->tag('console.command', ['command' => 'andreo:eventsauce:doctrine-migrations:generate'])
        ->private()
    ;
};
