<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EventStoreLoader
{
    public function __construct(private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $messageConfig = $config['event_store'];
        $messageRepositoryConfig = $messageConfig['repository'];
        $messageRepositoryDoctrineConfig = $messageRepositoryConfig['doctrine'];
        $connectionServiceId = $messageRepositoryDoctrineConfig['connection'];

        $this->container->setAlias('andreo.eventsauce.doctrine.connection', $connectionServiceId);

        $tableSchemaServiceId = $messageRepositoryDoctrineConfig['table_schema'];

        if (!in_array($tableSchemaServiceId, [null, TableSchema::class, DefaultTableSchema::class], true)) {
            $this->container->setAlias(TableSchema::class, $tableSchemaServiceId);
        }
    }
}
