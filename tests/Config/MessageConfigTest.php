<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauce\Messenger\MessengerEventAndHeadersDispatcher;
use Andreo\EventSauce\Messenger\MessengerEventDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageDispatcher;
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\MessageDispatchingEventDispatcher;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Reference;
use Tests\Config\Dummy\DummyCustomMessageSerializer;
use Tests\Config\Dummy\DummyCustomTableSchema;

final class MessageConfigTest extends AbstractExtensionTestCase
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
    public function should_register_message_components(): void
    {
        $this->load([
            'message' => [
                'repository' => [
                    'doctrine' => [
                        'connection' => 'doctrine.default_connection',
                        'table_schema' => DummyCustomTableSchema::class,
                    ],
                ],
                'serializer' => DummyCustomMessageSerializer::class,
                'decorator' => false,
            ],
        ]);

        $this->assertContainerBuilderHasAlias('andreo.event_sauce.doctrine.connection');
        $connectionAlias = $this->container->getAlias('andreo.event_sauce.doctrine.connection');
        $this->assertEquals('doctrine.default_connection', $connectionAlias->__toString());

        $this->assertContainerBuilderHasAlias(MessageSerializer::class);
        $serializerAlias = $this->container->getAlias(MessageSerializer::class);
        $this->assertEquals(DummyCustomMessageSerializer::class, $serializerAlias->__toString());

        $this->assertContainerBuilderHasAlias(TableSchema::class);
        $tableSchemaAlias = $this->container->getAlias(TableSchema::class);
        $this->assertEquals(DummyCustomTableSchema::class, $tableSchemaAlias->__toString());

        $this->assertArrayNotHasKey(AsMessageDecorator::class, $this->container->getAutoconfiguredAttributes());
    }

    /**
     * @test
     */
    public function should_register_message_dispatcher_with_event_and_headers_mode(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event_and_headers',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.event_sauce.message_dispatcher.fooBus',
            'andreo.event_sauce.event_and_headers_dispatcher',
            [
                'bus' => 'barBus',
            ]
        );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(MessengerEventAndHeadersDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('barBus'));

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.event_sauce.message_dispatcher.bazBus',
            'andreo.event_sauce.event_and_headers_dispatcher',
            [
                'bus' => 'quxBus',
            ]
        );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.bazBus');
        $this->assertEquals(MessengerEventAndHeadersDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('quxBus'));

        $this->assertContainerBuilderHasAlias('fooBus');
        $this->assertContainerBuilderHasAlias('bazBus');
        /** @var Alias $bazBusAlias */
        $bazBusAlias = $this->container->getAlias('bazBus');
        $bazBusDef = $this->container->getDefinition($bazBusAlias->__toString());
        $this->assertEquals(MessengerEventAndHeadersDispatcher::class, $bazBusDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_message_dispatcher_with_event_mode(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.fooBus', );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(MessengerEventDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('barBus'));

        $this->assertContainerBuilderHasAlias('fooBus');
    }

    /**
     * @test
     */
    public function should_register_message_dispatcher_with_message_mode(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'message',
                    ],
                    'chain' => [
                        'fooBus' => 'bazBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.fooBus', );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(MessengerMessageDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('bazBus'));

        $this->assertContainerBuilderHasAlias('fooBus');
    }

    /**
     * @test
     */
    public function should_register_standard_message_dispatcher(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'chain' => [
                        'fooBus',
                        'barBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.fooBus');
        $dispatcherDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(SynchronousMessageDispatcher::class, $dispatcherDefinition->getClass());

        $this->assertEquals(
            $dispatcherDefinition->getArgument(0),
            new TaggedIteratorArgument('andreo.event_sauce.message_consumer.fooBus')
        );

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.barBus');
        $dispatcherDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.barBus');
        $this->assertEquals(SynchronousMessageDispatcher::class, $dispatcherDefinition->getClass());
        $this->assertEquals(
            $dispatcherDefinition->getArgument(0),
            new TaggedIteratorArgument('andreo.event_sauce.message_consumer.barBus')
        );

        $this->assertContainerBuilderHasAlias('fooBus');
        $this->assertContainerBuilderHasAlias('barBus');

        /** @var Alias $barBusAlias */
        $barBusAlias = $this->container->getAlias('barBus');
        $barBusDef = $this->container->getDefinition($barBusAlias->__toString());
        $this->assertEquals(SynchronousMessageDispatcher::class, $barBusDef->getClass());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_messenger_bus_not_defined(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'message',
                    ],
                    'chain' => [
                        'fooBus' => null,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_throw_exception_if_dispatcher_alias_is_some_as_messenger_alias(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'message',
                    ],
                    'chain' => [
                        'fooBus' => 'fooBus',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_throw_exception_if_dispatcher_alias_is_numeric(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'message',
                    ],
                    'chain' => [
                        '1' => 'fooBus',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_register_standard_event_dispatcher(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'event_dispatcher' => true,
                    'chain' => [
                        'fooBus',
                        'barBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('fooBus');
        $this->assertContainerBuilderHasAlias('barBus');

        $fooBasAlias = $this->container->getAlias('fooBus');
        $fooBasDef = $this->container->getDefinition($fooBasAlias->__toString());
        $this->assertEquals(MessageDispatchingEventDispatcher::class, $fooBasDef->getClass());

        $fooBasAlias = $this->container->getAlias('barBus');
        $fooBasDef = $this->container->getDefinition($fooBasAlias->__toString());
        $this->assertEquals(MessageDispatchingEventDispatcher::class, $fooBasDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_messenger_event_dispatcher(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'mode' => 'event',
                    ],
                    'event_dispatcher' => true,
                    'chain' => [
                        'fooBus' => 'bazBus',
                        'bazBus' => 'quxBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('fooBus');
        $this->assertContainerBuilderHasAlias('bazBus');

        $fooBasAlias = $this->container->getAlias('fooBus');
        $fooBasDef = $this->container->getDefinition($fooBasAlias->__toString());
        $this->assertEquals(MessageDispatchingEventDispatcher::class, $fooBasDef->getClass());

        $fooBasAlias = $this->container->getAlias('bazBus');
        $fooBasDef = $this->container->getDefinition($fooBasAlias->__toString());
        $this->assertEquals(MessageDispatchingEventDispatcher::class, $fooBasDef->getClass());
    }
}
