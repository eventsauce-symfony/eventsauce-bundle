<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauce\Messenger\MessengerEventWithHeadersDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageEventDispatcher;
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Reference;
use Tests\ConfigExtension\Dummy\DummyCustomMessageSerializer;
use Tests\ConfigExtension\Dummy\DummyCustomTableSchema;

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
    public function message_config_is_loading(): void
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

        $this->assertArrayNotHasKey(AsMessageDecorator::class, $this->container->getAutoconfiguredAttributes());
    }

    /**
     * @test
     */
    public function messenger_message_dispatcher_of_mode_event_with_headers_is_loading(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'event_with_headers',
                    ],
                    'chain' => [
                        'fooBus' => 'barBus',
                        'barBus' => 'bazBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.event_sauce.message_dispatcher.fooBus',
            'andreo.event_sauce.event_with_headers_dispatcher',
            [
                'bus' => 'barBus',
            ]
        );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(MessengerEventWithHeadersDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('barBus'));

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.event_sauce.message_dispatcher.barBus',
            'andreo.event_sauce.event_with_headers_dispatcher',
            [
                'bus' => 'bazBus',
            ]
        );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.barBus');
        $this->assertEquals(MessengerEventWithHeadersDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('bazBus'));
    }

    /**
     * @test
     */
    public function messenger_message_dispatcher_of_mode_event_is_loading(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
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
        $this->assertEquals(MessengerMessageEventDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('barBus'));
    }

    /**
     * @test
     */
    public function messenger_message_dispatcher_of_mode_message_is_loading(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
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
    }

    /**
     * @test
     */
    public function default_message_dispatcher_config_is_loading(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'chain' => ['fooBus', 'barBus'],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('fooBus');
        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.fooBus');
        $dispatcherDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.fooBus');
        $this->assertEquals(SynchronousMessageDispatcher::class, $dispatcherDefinition->getClass());

        $this->assertEquals(
            $dispatcherDefinition->getArgument(0),
            new TaggedIteratorArgument('andreo.event_sauce.message_consumer.fooBus')
        );

        $this->assertContainerBuilderHasAlias('barBus');
        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.barBus');
        $dispatcherDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.barBus');
        $this->assertEquals(SynchronousMessageDispatcher::class, $dispatcherDefinition->getClass());
        $this->assertEquals(
            $dispatcherDefinition->getArgument(0),
            new TaggedIteratorArgument('andreo.event_sauce.message_consumer.barBus')
        );
    }

    /**
     * @test
     */
    public function messenger_message_dispatcher_config_throw_exception_if_bus_not_defined(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
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
    public function message_decorator_config_is_loading(): void
    {
        $this->load([
            'message' => [
                'decorator' => false,
            ],
        ]);

        $this->assertArrayNotHasKey(AsMessageDecorator::class, $this->container->getAutoconfiguredAttributes());
    }
}
