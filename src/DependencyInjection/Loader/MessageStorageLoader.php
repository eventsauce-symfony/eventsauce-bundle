<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MessageStorageLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $messageStorageConfig = $config['message_storage'];
        $messageRepositoryConfig = $messageStorageConfig['repository'];
        $memoryRepositoryEnabled = $this->extension->isConfigEnabled(
            $this->container,
            $messageRepositoryConfig['memory']
        );
        if ($memoryRepositoryEnabled) {
            return;
        }

        $messageRepositoryDoctrineConfig = $messageRepositoryConfig['doctrine'];
        $connectionServiceId = $messageRepositoryDoctrineConfig['connection'];
        $this->container->setAlias('andreo.eventsauce.doctrine.connection', $connectionServiceId);

        $tableSchemaServiceId = $messageRepositoryDoctrineConfig['table_schema'];

        if (!in_array($tableSchemaServiceId, [null, TableSchema::class, DefaultTableSchema::class], true)) {
            $this->container->setAlias(TableSchema::class, $tableSchemaServiceId);
        }
    }
}
