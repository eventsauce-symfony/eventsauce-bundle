<?php

declare(strict_types=1);

namespace Tests\DefaultConfig;

use Symfony\Component\DependencyInjection\Exception\LogicException;

final class MessengerMessageDispatcherTest
{
    /**
     * @test
     */
    public function should_load_dispatchers(): void
    {
        $this->load([
            'messenger_message_dispatcher' => [
                'chain' => [
                    'foo' => [
                        'bus' => 'bar',
                    ],
                    'bar' => [
                        'baz' => 'foo',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_dispatcher.foo');
        $this->assertContainerBuilderHasService('andreo.eventsauce.message_dispatcher.bar');
        $this->assertContainerBuilderHasAlias('foo');
        $this->assertContainerBuilderHasAlias('bar');

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_dispatcher_chain');
    }

    /**
     * @test
     */
    public function should_load_dispatcher_with_acl(): void
    {
        $this->load([
            'messenger_message_dispatcher' => [
                'chain' => [
                    'foo' => [
                        'bus' => 'bar',
                        'acl' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'andreo.eventsauce.message_dispatcher.foo',
            'andreo.eventsauce.acl.filter_chain',
            [
                'before' => 'match_all',
                'after' => 'match_all',
            ]
        );
    }

    /**
     * @test
     */
    public function should_throw_exception_when_dispatcher_acl_is_enabled_but_main_acl_is_disabled(): void
    {
        $this->expectException(LogicException::class);

        $this->load([
            'acl' => false,
            'messenger_message_dispatcher' => [
                'chain' => [
                    'foo' => [
                        'acl' => true,
                        'bus' => 'bar',
                    ],
                ],
            ],
        ]);

        $this->expectException(LogicException::class);

        $this->load([
            'acl' => [
                'outbound' => false,
            ],
            'messenger_message_dispatcher' => [
                'chain' => [
                    'foo' => [
                        'acl' => true,
                        'bus' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_throw_exception_if_synchronous_dispatcher_is_enabled(): void
    {
        $this->expectException(LogicException::class);

        $this->load([
            'messenger_message_dispatcher' => [],
            'synchronous_message_dispatcher' => [],
        ]);
    }
}
