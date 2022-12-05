<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Andreo\EventSauce\Outbox\Command\OutboxMessagesConsumeCommand;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\BackOff\ExponentialBackOffStrategy;
use EventSauce\BackOff\FibonacciBackOffStrategy;
use EventSauce\BackOff\ImmediatelyFailingBackOffStrategy;
use EventSauce\BackOff\LinearBackOffStrategy;
use EventSauce\BackOff\NoWaitingBackOffStrategy;
use EventSauce\MessageOutbox\DeleteMessageOnCommit;
use EventSauce\MessageOutbox\MarkMessagesConsumedOnCommit;
use EventSauce\MessageOutbox\RelayCommitStrategy;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $parameters = $container->parameters();

    $parameters->set('andreo.eventsauce.outbox.back_off.initial_delay_ms', 100000);
    $parameters->set('andreo.eventsauce.outbox.back_off.max_tries', 15);
    $parameters->set('andreo.eventsauce.outbox.back_off.max_delay', 2500000);
    $parameters->set('andreo.eventsauce.outbox.back_off.base', 2.5);

    $services->alias(BackOffStrategy::class, ExponentialBackOffStrategy::class);
    $services->alias(RelayCommitStrategy::class, MarkMessagesConsumedOnCommit::class);

    $services
        ->set(ExponentialBackOffStrategy::class, ExponentialBackOffStrategy::class)
        ->args([
            param('andreo.eventsauce.outbox.back_off.initial_delay_ms'),
            param('andreo.eventsauce.outbox.back_off.max_tries'),
            param('andreo.eventsauce.outbox.back_off.max_delay'),
            param('andreo.eventsauce.outbox.back_off.base'),
        ])
        ->lazy()
        ->private()
    ;

    $services
        ->set(FibonacciBackOffStrategy::class, FibonacciBackOffStrategy::class)
        ->args([
            param('andreo.eventsauce.outbox.back_off.initial_delay_ms'),
            param('andreo.eventsauce.outbox.back_off.max_tries'),
            param('andreo.eventsauce.outbox.back_off.max_delay'),
        ])
        ->lazy()
        ->private()
    ;

    $services
        ->set(LinearBackOffStrategy::class, LinearBackOffStrategy::class)
        ->args([
            param('andreo.eventsauce.outbox.back_off.initial_delay_ms'),
            param('andreo.eventsauce.outbox.back_off.max_tries'),
            param('andreo.eventsauce.outbox.back_off.max_delay'),
        ])
        ->lazy()
        ->private()
    ;

    $services
        ->set(NoWaitingBackOffStrategy::class, NoWaitingBackOffStrategy::class)
        ->args([
            param('andreo.eventsauce.outbox.back_off.max_tries'),
        ])
        ->lazy()
        ->private()
    ;

    $services
        ->set(ImmediatelyFailingBackOffStrategy::class, ImmediatelyFailingBackOffStrategy::class)
        ->lazy()
        ->private()
    ;

    $services
        ->set(MarkMessagesConsumedOnCommit::class, MarkMessagesConsumedOnCommit::class)
        ->lazy()
        ->private()
    ;

    $services
        ->set(DeleteMessageOnCommit::class, DeleteMessageOnCommit::class)
        ->lazy()
        ->private()
    ;

    $services
        ->set(OutboxMessagesConsumeCommand::class, OutboxMessagesConsumeCommand::class)
        ->args([
            tagged_locator('andreo.eventsauce.outbox_relay', 'relay_id'),
            service('andreo.eventsauce.outbox.logger')->nullOnInvalid(),
        ])
        ->tag('console.command', ['command' => 'andreo:eventsauce:message-outbox:consume'])
        ->private()
    ;
};
