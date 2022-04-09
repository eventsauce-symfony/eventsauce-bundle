<?php

declare(strict_types=1);

namespace Tests\DefaultConfig;

use Andreo\EventSauceBundle\Attribute\AsSynchronousMessageConsumer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class SynchronousMessageDispatcherTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_dispatcher_components(): void
    {
        $this->load([
            'synchronous_message_dispatcher' => [],
        ]);

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(AsSynchronousMessageConsumer::class, $attributes);
    }

    /**
     * @test
     */
    public function should_load_dispatchers(): void
    {
        $this->load([
            'synchronous_message_dispatcher' => [
                'chain' => [
                    'foo',
                    'bar',
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
            'acl' => true,
            'synchronous_message_dispatcher' => [
                'chain' => [
                    'foo' => [
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
            'synchronous_message_dispatcher' => [
                'chain' => [
                    'foo' => [
                        'acl' => true,
                    ],
                ],
            ],
        ]);

        $this->expectException(LogicException::class);

        $this->load([
            'acl' => [
                'outbound' => false,
            ],
            'synchronous_message_dispatcher' => [
                'chain' => [
                    'foo' => [
                        'acl' => true,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_throw_exception_if_messenger_dispatcher_is_enabled(): void
    {
        $this->expectException(LogicException::class);

        $this->load([
            'messenger_message_dispatcher' => [],
            'synchronous_message_dispatcher' => [],
        ]);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
