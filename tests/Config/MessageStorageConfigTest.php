<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Tests\Dummy\DummyTableSchema;

final class MessageStorageConfigTest extends AbstractExtensionTestCase
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
    public function should_load_message_storage(): void
    {
        $this->load([
            'message_storage' => [
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

    /**
     * @test
     */
    public function should_throw_exception_when_more_than_one_repository_is_enabled(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'message_storage' => [
                'repository' => [
                    'memory' => true,
                    'doctrine' => true,
                ],
            ],
        ]);
    }
}
