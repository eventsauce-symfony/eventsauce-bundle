<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

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
    public function should_load_default_message_storage_config(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias('andreo.eventsauce.doctrine.connection');
        $connectionAlias = $this->container->getAlias('andreo.eventsauce.doctrine.connection');
        $this->assertEquals('doctrine.dbal.default_connection', $connectionAlias->__toString());
        $this->assertContainerBuilderHasAlias(TableSchema::class);
    }

    /**
     * @test
     */
    public function should_load_changed_message_storage_config(): void
    {
        $this->load([
            'message_storage' => [
                'repository' => [
                    'doctrine_3' => [
                        'connection' => 'doctrine.default_connection',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('andreo.eventsauce.doctrine.connection');
        $connectionAlias = $this->container->getAlias('andreo.eventsauce.doctrine.connection');
        $this->assertEquals('doctrine.default_connection', $connectionAlias->__toString());
    }
}
