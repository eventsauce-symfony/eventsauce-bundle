<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class MessageDispatcherConfigTest extends AbstractExtensionTestCase
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
    public function should_load_message_dispatcher_config(): void
    {
        $this->load([
            'message_dispatcher' => [
                'foo_dispatcher' => [
                    'type' => [
                        'sync' => true,
                    ],
                ],
                'bar_dispatcher' => [
                    'type' => [
                        'messenger' => [
                            'bus' => 'barBus',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('foo_dispatcher');
        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.eventsauce.message_dispatcher.foo_dispatcher',
            'andreo.eventsauce.message_dispatcher'
        );

        $this->assertContainerBuilderHasAlias('bar_dispatcher');
        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.eventsauce.message_dispatcher.bar_dispatcher',
            'andreo.eventsauce.message_dispatcher'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'barBus.middleware.handle_event_sauce_message',
            'monolog.logger',
            [
                'channel' => 'messenger',
            ]
        );

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_dispatcher_chain');
    }

    /**
     * @test
     */
    public function should_load_message_dispatcher_config_with_acl(): void
    {
        $this->load([
            'acl' => true,
            'message_dispatcher' => [
                'foo_dispatcher' => [
                    'type' => [
                        'sync' => true,
                    ],
                    'acl' => true,
                ],
                'bar_dispatcher' => [
                    'type' => [
                        'messenger' => [
                            'bus' => 'barBus',
                        ],
                    ],
                    'acl' => [
                        'message_filter_strategy' => [
                            'before_translate' => 'match_any',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.eventsauce.message_dispatcher.foo_dispatcher',
            'andreo.eventsauce.acl',
            [
                'message_filter_strategy_before_translate' => 'match_all',
                'message_filter_strategy_after_translate' => 'match_all',
            ]
        );

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.eventsauce.message_dispatcher.bar_dispatcher',
            'andreo.eventsauce.acl',
            [
                'message_filter_strategy_before_translate' => 'match_any',
                'message_filter_strategy_after_translate' => 'match_all',
            ]
        );
    }

    /**
     * @test
     */
    public function should_throw_exception_if_more_than_one_message_dispatcher_type_has_been_defined(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'message_dispatcher' => [
                'foo_dispatcher' => [
                    'type' => [
                        'sync' => true,
                        'messenger' => [
                            'bus' => 'fooBus',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_throw_exception_if_message_dispatcher_type_has_not_been_defined(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'message_dispatcher' => [
                'foo_dispatcher' => [
                    'type' => [
                        'sync' => [
                            'enabled' => false,
                        ],
                        'messenger' => [
                            'bus' => 'null',
                            'enabled' => false,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_throw_exception_if_message_dispatcher_alias_not_string(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'message_dispatcher' => [
                0 => [
                    'type' => [
                        'messenger' => [
                            'bus' => 'fooBus',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
