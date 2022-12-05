<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Andreo\EventSauce\Snapshotting\Conditional\AlwaysConditionalSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\Conditional\ConditionalSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\Doctrine\Table\DefaultSnapshotTableSchema;
use Andreo\EventSauce\Snapshotting\Doctrine\Table\SnapshotTableSchema;
use Andreo\EventSauce\Snapshotting\Serializer\ConstructingSnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\Serializer\SnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\Versioned\EqSnapshotVersionComparator;
use Andreo\EventSauce\Snapshotting\Versioned\InflectVersionFromReturnedTypeOfSnapshotStateCreationMethod;
use Andreo\EventSauce\Snapshotting\Versioned\SnapshotVersionComparator;
use Andreo\EventSauce\Snapshotting\Versioned\SnapshotVersionInflector;
use EventSauce\Clock\Clock;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $parameters = $container->parameters();

    $parameters->set('andreo.eventsauce.snapshot.doctrine_repository.json_encode_flags', []);
    $parameters->set('andreo.eventsauce.snapshot.doctrine_repository.json_decode_flags', []);
    $parameters->set('andreo.eventsauce.snapshot.doctrine_repository.json_depth', 512);

    $services->alias(SnapshotStateSerializer::class, ConstructingSnapshotStateSerializer::class);
    $services->alias(SnapshotVersionInflector::class, InflectVersionFromReturnedTypeOfSnapshotStateCreationMethod::class);
    $services->alias(SnapshotVersionComparator::class, EqSnapshotVersionComparator::class);
    $services->alias(ConditionalSnapshotStrategy::class, AlwaysConditionalSnapshotStrategy::class);
    $services->alias(SnapshotTableSchema::class, DefaultSnapshotTableSchema::class);

    $services
        ->set(ConstructingSnapshotStateSerializer::class, ConstructingSnapshotStateSerializer::class)
        ->args([
            service(PayloadSerializer::class),
            service(Clock::class),
            service(ClassNameInflector::class),
        ])
        ->private()
    ;

    $services
        ->set(DefaultSnapshotTableSchema::class, DefaultSnapshotTableSchema::class)
        ->private()
    ;

    $services
        ->set(InflectVersionFromReturnedTypeOfSnapshotStateCreationMethod::class, InflectVersionFromReturnedTypeOfSnapshotStateCreationMethod::class)
        ->private()
    ;

    $services
        ->set(EqSnapshotVersionComparator::class, EqSnapshotVersionComparator::class)
        ->private()
    ;

    $services
        ->set(AlwaysConditionalSnapshotStrategy::class, AlwaysConditionalSnapshotStrategy::class)
        ->private()
    ;
};
