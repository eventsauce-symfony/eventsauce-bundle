<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\BackOff\ExponentialBackOffStrategy;
use EventSauce\BackOff\FibonacciBackOffStrategy;
use EventSauce\BackOff\ImmediatelyFailingBackOffStrategy;
use EventSauce\BackOff\LinearBackOffStrategy;
use EventSauce\BackOff\NoWaitingBackOffStrategy;
use EventSauce\MessageOutbox\DeleteMessageOnCommit;
use EventSauce\MessageOutbox\MarkMessagesConsumedOnCommit;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Tests\Config\Dummy\DummyCustomBackOfStrategy;

final class OutboxConfigTest extends AbstractExtensionTestCase
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
    public function should_register_exponential_back_of_strategy(): void
    {
        $this->load([
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
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(ExponentialBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $exponentialDefinition = $this->container->getDefinition(ExponentialBackOffStrategy::class);
        $this->assertEquals(200000, $exponentialDefinition->getArgument(0));
        $this->assertEquals(20, $exponentialDefinition->getArgument(1));
    }

    /**
     * @test
     */
    public function should_register_fibonacci_back_of_strategy(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'fibonacci' => [
                        'enabled' => true,
                        'max_tries' => 30,
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
    }

    /**
     * @test
     */
    public function should_register_linear_back_of_strategy(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'linear' => [
                        'enabled' => true,
                        'initial_delay_ms' => 300000,
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
    }

    /**
     * @test
     */
    public function should_register_no_waiting_back_of_strategy(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'no_waiting' => [
                        'enabled' => true,
                        'max_tries' => 20,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(NoWaitingBackOffStrategy::class, $backOffStrategyAlias->__toString());
        $noWaitingDefinition = $this->container->getDefinition(NoWaitingBackOffStrategy::class);
        $this->assertEquals(20, $noWaitingDefinition->getArgument(0));
    }

    /**
     * @test
     */
    public function should_register_immediately_back_of_strategy(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'immediately' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(ImmediatelyFailingBackOffStrategy::class, $backOffStrategyAlias->__toString());
    }

    /**
     * @test
     */
    public function should_register_custom_back_of_strategy(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'back_off' => [
                    'custom' => [
                        'id' => DummyCustomBackOfStrategy::class,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(DummyCustomBackOfStrategy::class, $backOffStrategyAlias->__toString());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_more_than_one_back_off_strategy_defined(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
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
        ]);
    }

    /**
     * @test
     */
    public function should_register_deleted_relay_commit_strategy(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'relay_commit' => [
                    'delete' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(RelayCommitStrategy::class);
        $relayCommitStrategyAlias = $this->container->getAlias(RelayCommitStrategy::class);
        $this->assertEquals(DeleteMessageOnCommit::class, $relayCommitStrategyAlias->__toString());
    }

    /**
     * @test
     */
    public function should_register_mark_consumed_relay_commit_strategy(): void
    {
        $this->load([
            'outbox' => [
                'enabled' => true,
                'relay_commit' => [
                    'mark_consumed' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias(RelayCommitStrategy::class);
        $relayCommitStrategyAlias = $this->container->getAlias(RelayCommitStrategy::class);
        $this->assertEquals(MarkMessagesConsumedOnCommit::class, $relayCommitStrategyAlias->__toString());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_more_than_one_relay_commit_strategy_defined(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
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
        ]);
    }
}
