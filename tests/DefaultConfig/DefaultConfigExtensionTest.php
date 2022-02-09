<?php

declare(strict_types=1);

namespace Tests\DefaultConfig;

use Andreo\EventSauce\Doctrine\Migration\TableNameSuffix;
use Andreo\EventSauce\Messenger\MessengerEventDispatcher;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauceBundle\Attribute\AsMessageConsumer;
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\Clock\Clock;
use EventSauce\Clock\SystemClock;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use EventSauce\MessageOutbox\RelayCommitStrategy;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use EventSauce\UuidEncoding\UuidEncoder;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class DefaultConfigExtensionTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_register_time_components(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'andreo.event_sauce.recording_timezone',
            0,
            'UTC'
        );

        $this->assertContainerBuilderHasAlias(Clock::class);
        $clockAlias = $this->container->getAlias(Clock::class);
        $this->assertEquals(SystemClock::class, $clockAlias->__toString());
    }

    /**
     * @test
     */
    public function should_register_message_components(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias('andreo.event_sauce.doctrine.connection');
        $this->assertContainerBuilderHasAlias(TableSchema::class);
        $this->assertContainerBuilderHasAlias(MessageSerializer::class);

        $this->assertArrayHasKey(AsMessageDecorator::class, $this->container->getAutoconfiguredAttributes());
        $this->assertArrayHasKey(AsMessageConsumer::class, $this->container->getAutoconfiguredAttributes());
    }

    /**
     * @test
     */
    public function should_register_these_components(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias(UuidEncoder::class);
        $this->assertContainerBuilderHasAlias(ClassNameInflector::class);
        $this->assertContainerBuilderHasAlias(PayloadSerializer::class);

        $this->assertContainerBuilderHasService(TableNameSuffix::class);
    }

    /**
     * @test
     */
    public function should_not_register_these_components(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(MessengerEventDispatcher::class);

        $this->assertContainerBuilderNotHasService(BackOffStrategy::class);
        $this->assertContainerBuilderNotHasService(RelayCommitStrategy::class);

        $this->assertContainerBuilderNotHasService(SnapshotStateSerializer::class);

        $this->assertArrayNotHasKey(AsUpcaster::class, $this->container->getAutoconfiguredAttributes());
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
