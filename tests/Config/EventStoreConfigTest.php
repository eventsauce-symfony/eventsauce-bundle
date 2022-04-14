<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Tests\Dummy\DummyTableSchema;

final class EventStoreConfigTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }

    /**
     * @test
     */
    public function should_load_event_store(): void
    {
        $this->load([
            'event_store' => [
                'repository' => [
                    'doctrine' => [
                        'connection' => 'doctrine.default_connection',
                        'table_schema' => DummyTableSchema::class,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('andreo.eventsauce.doctrine.connection');
        $connectionAlias = $this->container->getAlias('andreo.eventsauce.doctrine.connection');
        $this->assertEquals('doctrine.default_connection', $connectionAlias->__toString());

        $this->assertContainerBuilderHasAlias(TableSchema::class);
        $tableSchemaAlias = $this->container->getAlias(TableSchema::class);
        $this->assertEquals(DummyTableSchema::class, $tableSchemaAlias->__toString());
    }
}
