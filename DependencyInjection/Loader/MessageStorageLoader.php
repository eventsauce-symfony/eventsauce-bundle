<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class MessageStorageLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        array $config
    ): void {
        $messageStorageEnabled = $extension->isConfigEnabled(
            $container,
            $messageStorageConfig = $config['message_storage']
        );
        if (!$messageStorageEnabled) {
            return;
        }

        $messageRepositoryConfig = $messageStorageConfig['repository'];
        $doctrineRepositoryEnabled = $extension->isConfigEnabled(
            $container,
            $messageRepositoryDoctrineConfig = $messageRepositoryConfig['doctrine_3']
        );
        if (!$doctrineRepositoryEnabled) {
            return;
        }
        $connectionServiceId = $messageRepositoryDoctrineConfig['connection'];
        $container->setAlias('andreo.eventsauce.doctrine.connection', $connectionServiceId);
    }
}
