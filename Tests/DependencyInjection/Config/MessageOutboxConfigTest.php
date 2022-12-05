<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\BackOff\ExponentialBackOffStrategy;
use EventSauce\MessageOutbox\MarkMessagesConsumedOnCommit;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class MessageOutboxConfigTest extends AbstractExtensionTestCase
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
    public function should_load_default_outbox_config(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(BackOffStrategy::class);
        $this->assertContainerBuilderNotHasService(RelayCommitStrategy::class);
    }

    /**
     * @test
     */
    public function should_load_outbox_config(): void
    {
        $this->load([
            'message_outbox' => [
                'logger' => 'foo_logger',
            ],
        ]);

        $this->assertContainerBuilderHasAlias(BackOffStrategy::class);
        $this->assertContainerBuilderHasAlias(RelayCommitStrategy::class);
        $this->assertContainerBuilderHasAlias('andreo.eventsauce.outbox.logger');

        $backOffStrategyAlias = $this->container->getAlias(BackOffStrategy::class);
        $this->assertEquals(ExponentialBackOffStrategy::class, $backOffStrategyAlias->__toString());

        $backOffStrategyAlias = $this->container->getAlias(RelayCommitStrategy::class);
        $this->assertEquals(MarkMessagesConsumedOnCommit::class, $backOffStrategyAlias->__toString());
    }
}
