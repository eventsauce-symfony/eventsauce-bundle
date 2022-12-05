<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Outbox\Repository\EventSourcedAggregateRootRepositoryForOutbox;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\FileLoader;

final readonly class MessageOutboxLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        FileLoader $loader,
        array $config
    ): void {
        $outboxConfig = $config['message_outbox'];
        if (!$extension->isConfigEnabled($container, $outboxConfig)) {
            return;
        }
        if (!class_exists(EventSourcedAggregateRootRepositoryForOutbox::class)) {
            throw new LogicException('Message outbox is not available. Try running "composer require andreo/eventsauce-outbox".');
        }

        $loader->load('outbox.php');

        if (null !== $loggerAlias = $outboxConfig['logger']) {
            $container->setAlias('andreo.eventsauce.outbox.logger', $loggerAlias);
        }
    }
}
