<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EventSauce\Clock\Clock;
use EventSauce\Clock\SystemClock;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector;
use EventSauce\EventSourcing\EventConsumption\HandleMethodInflector;
use EventSauce\EventSourcing\EventConsumption\InflectHandlerMethodsFromType;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Serialization\MySQL8DateFormatting;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use EventSauce\UuidEncoding\BinaryUuidEncoder;
use EventSauce\UuidEncoding\StringUuidEncoder;
use EventSauce\UuidEncoding\UuidEncoder;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $parameters = $container->parameters();

    $parameters->set('andreo.serializer.message.time_of_recording_format', Message::TIME_OF_RECORDING_FORMAT);

    $services->alias(Clock::class, SystemClock::class);
    $services->alias(TableSchema::class, DefaultTableSchema::class);
    $services->alias(PayloadSerializer::class, ConstructingPayloadSerializer::class);
    $services->alias(MessageSerializer::class, ConstructingMessageSerializer::class);
    $services->alias(UuidEncoder::class, BinaryUuidEncoder::class);
    $services->alias(ClassNameInflector::class, DotSeparatedSnakeCaseInflector::class);
    $services->alias(HandleMethodInflector::class, InflectHandlerMethodsFromType::class);

    $services
        ->set(SystemClock::class, SystemClock::class)
        ->args([
            service('andreo.eventsauce.clock.timezone'),
        ])
        ->private()
    ;

    $services
        ->set(DefaultTableSchema::class, DefaultTableSchema::class)
        ->private()
    ;

    $services
        ->set(ConstructingPayloadSerializer::class, ConstructingPayloadSerializer::class)
        ->private()
    ;

    $services
        ->set(ConstructingMessageSerializer::class, ConstructingMessageSerializer::class)
        ->args([
            service(ClassNameInflector::class),
            service(PayloadSerializer::class),
        ])
        ->private()
    ;

    $services
        ->set(MySQL8DateFormatting::class, MySQL8DateFormatting::class)
        ->args([
            service(ConstructingMessageSerializer::class),
        ])
        ->private()
    ;

    $services
        ->set(BinaryUuidEncoder::class, BinaryUuidEncoder::class)
        ->private()
    ;

    $services
        ->set(StringUuidEncoder::class, StringUuidEncoder::class)
        ->private()
    ;

    $services
        ->set(DotSeparatedSnakeCaseInflector::class, DotSeparatedSnakeCaseInflector::class)
        ->lazy()
        ->private()
    ;

    $services
        ->set(InflectHandlerMethodsFromType::class, InflectHandlerMethodsFromType::class)
        ->lazy()
        ->private()
    ;

    $services
        ->set(DefaultHeadersDecorator::class, DefaultHeadersDecorator::class)
        ->args([
            service(ClassNameInflector::class),
            service(Clock::class),
            param('andreo.serializer.message.time_of_recording_format'),
        ])
        ->private()
    ;
};
