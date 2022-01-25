<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauce\Messenger\MessengerEventWithHeadersDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageDispatcher;
use Andreo\EventSauce\Messenger\MessengerMessageEventDispatcher;
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\DelegatingSynchronousMessageDispatcher;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\BackOff\ExponentialBackOffStrategy;
use EventSauce\BackOff\FibonacciBackOffStrategy;
use EventSauce\BackOff\ImmediatelyFailingBackOffStrategy;
use EventSauce\BackOff\LinearBackOffStrategy;
use EventSauce\BackOff\NoWaitingBackOffStrategy;
use EventSauce\Clock\Clock;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\MessageOutbox\DeleteMessageOnCommit;
use EventSauce\MessageOutbox\MarkMessagesConsumedOnCommit;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Reference;

final class EventSauceConfigExtensionTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function time_config_is_valid_loading(): void
    {
        $this->load([
            'time' => [
                'recording_timezone' => 'Europe/Warsaw',
                'clock' => DummyCustomClock::class,
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'andreo.event_sauce.recording_timezone',
            0,
            'Europe/Warsaw'
        );

        $this->assertContainerBuilderHasAlias(Clock::class);
        $clockAlias = $this->container->getAlias(Clock::class);
        $this->assertEquals(DummyCustomClock::class, $clockAlias->__toString());
    }

    /**
     * @test
     */
    public function message_config_is_valid_loading(): void
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
    public function messenger_message_dispatcher_config_is_valid_loading(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'event_with_headers',
                    ],
                    'chain' => [
                        'foo_bus' => 'fooBus',
                        'bar_bus' => 'barBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.event_sauce.message_dispatcher.foo_bus',
            'andreo.event_sauce.event_with_headers_dispatcher',
            [
                'bus' => 'fooBus',
            ]
        );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.foo_bus');
        $this->assertEquals(MessengerEventWithHeadersDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('fooBus'));

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.event_sauce.message_dispatcher.bar_bus',
            'andreo.event_sauce.event_with_headers_dispatcher',
            [
                'bus' => 'barBus',
            ]
        );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.bar_bus');
        $this->assertEquals(MessengerEventWithHeadersDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('barBus'));

        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'event',
                    ],
                    'chain' => [
                        'foo_bus' => 'fooBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.foo_bus', );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.foo_bus');
        $this->assertEquals(MessengerMessageEventDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('fooBus'));

        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                        'mode' => 'message',
                    ],
                    'chain' => [
                        'foo_bus' => 'fooBus',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.foo_bus', );
        $busDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.foo_bus');
        $this->assertEquals(MessengerMessageDispatcher::class, $busDefinition->getClass());
        $this->assertEquals($busDefinition->getArgument(0), new Reference('fooBus'));

        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'message' => [
                'dispatcher' => [
                    'messenger' => [
                        'enabled' => true,
                    ],
                    'chain' => [
                        'foo_bus' => null,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function message_dispatcher_config_is_valid_loading(): void
    {
        $this->load([
            'message' => [
                'dispatcher' => [
                    'chain' => [
                        'foo_service' => 'default',
                        'bar_service' => null,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('foo_service');
        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.foo_service');
        $dispatcherDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.foo_service');
        $this->assertEquals(DelegatingSynchronousMessageDispatcher::class, $dispatcherDefinition->getClass());
        $this->assertEquals(
            $dispatcherDefinition->getArgument(0),
            new TaggedIteratorArgument('andreo.event_sauce.message_consumer.foo_service')
        );

        $this->assertContainerBuilderHasAlias('bar_service');
        $this->assertContainerBuilderHasService('andreo.event_sauce.message_dispatcher.bar_service');
        $dispatcherDefinition = $this->container->getDefinition('andreo.event_sauce.message_dispatcher.bar_service');
        $this->assertEquals(DelegatingSynchronousMessageDispatcher::class, $dispatcherDefinition->getClass());
        $this->assertEquals(
            $dispatcherDefinition->getArgument(0),
            new TaggedIteratorArgument('andreo.event_sauce.message_consumer.bar_service')
        );
    }

    /**
     * @test
     */
    public function message_decorator_config_is_valid_loading(): void
    {
        $this->load([
            'message' => [
                'decorator' => false,
            ],
        ]);

        $this->assertArrayNotHasKey(AsMessageDecorator::class, $this->container->getAutoconfiguredAttributes());
    }

    /**
     * @test
     */
    public function outbox_back_of_config_is_valid_loading(): void
    {
        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'back_off' => [
                        'exponential' => [
                            'enabled' => true,
                            'initial_delay_ms' => 200000,
                            'max_tries' => 20,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(ExponentialBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $exponentialDefinition = $this->container->getDefinition(ExponentialBackOffStrategy::class);
        $this->assertEquals(200000, $exponentialDefinition->getArgument(0));
        $this->assertEquals(20, $exponentialDefinition->getArgument(1));

        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'back_off' => [
                        'fibonacci' => [
                            'enabled' => true,
                            'max_tries' => 30,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(FibonacciBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $fibonacciDefinition = $this->container->getDefinition(FibonacciBackOffStrategy::class);
        $this->assertEquals(
            '%andreo.event_sauce.outbox.back_off.initial_delay_ms%',
            $fibonacciDefinition->getArgument(0)
        );
        $this->assertEquals(30, $fibonacciDefinition->getArgument(1));

        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'back_off' => [
                        'linear_back' => [
                            'enabled' => true,
                            'initial_delay_ms' => 300000,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(LinearBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $linearDefinition = $this->container->getDefinition(LinearBackOffStrategy::class);
        $this->assertEquals(300000, $linearDefinition->getArgument(0));
        $this->assertEquals('%andreo.event_sauce.outbox.back_off.max_tries%', $linearDefinition->getArgument(1));

        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'back_off' => [
                        'no_waiting' => [
                            'enabled' => true,
                            'max_tries' => 20,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(NoWaitingBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $noWaitingDefinition = $this->container->getDefinition(NoWaitingBackOffStrategy::class);
        $this->assertEquals(20, $noWaitingDefinition->getArgument(0));

        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'back_off' => [
                        'immediately_failing' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(ImmediatelyFailingBackOffStrategy::class, $backOffStrategyAlias->__toString());

        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'back_off' => [
                        'custom' => [
                            'id' => DummyBackOfStrategy::class,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(DummyBackOfStrategy::class, $backOffStrategyAlias->__toString());

        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'back_off' => [
                        'exponential' => [
                            'enabled' => true,
                            'initial_delay_ms' => 200000,
                            'max_tries' => 20,
                        ],
                        'no_waiting' => [
                            'enabled' => true,
                            'max_tries' => 20,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function outbox_relay_commit_config_is_valid_loading(): void
    {
        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'relay_commit' => [
                        'delete' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(RelayCommitStrategy::class);
        $relayCommitStrategyAlias = $this->container->getAlias(RelayCommitStrategy::class);
        $this->assertEquals(DeleteMessageOnCommit::class, $relayCommitStrategyAlias->__toString());

        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'relay_commit' => [
                        'mark_consumed' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(RelayCommitStrategy::class);
        $relayCommitStrategyAlias = $this->container->getAlias(RelayCommitStrategy::class);
        $this->assertEquals(MarkMessagesConsumedOnCommit::class, $relayCommitStrategyAlias->__toString());

        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'message' => [
                'outbox' => [
                    'enabled' => true,
                    'relay_commit' => [
                        'delete' => [
                            'enabled' => true,
                        ],
                        'mark_consumed' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
