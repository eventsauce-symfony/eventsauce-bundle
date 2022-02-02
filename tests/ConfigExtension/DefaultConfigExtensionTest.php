<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauce\Doctrine\Migration\GenerateAggregateMigrationCommand;
use Andreo\EventSauce\Doctrine\Migration\TableNameSuffix;
use Andreo\EventSauce\Messenger\MessengerMessageEventDispatcher;
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
    public function default_time_config_is_loading(): void
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
    public function default_message_config_is_loading(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias('andreo.event_sauce.doctrine.connection');
        $this->assertContainerBuilderHasAlias(TableSchema::class);
        $this->assertContainerBuilderHasAlias(MessageSerializer::class);

        $this->assertArrayHasKey(AsMessageDecorator::class, $this->container->getAutoconfiguredAttributes());
        $this->assertArrayHasKey(AsMessageConsumer::class, $this->container->getAutoconfiguredAttributes());

        $this->assertContainerBuilderNotHasService(MessengerMessageEventDispatcher::class);
        $this->assertContainerBuilderNotHasService(BackOffStrategy::class);
        $this->assertContainerBuilderNotHasService(RelayCommitStrategy::class);
    }

    /**
     * @test
     */
    public function default_snapshot_config_is_loading(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(SnapshotStateSerializer::class);
    }

    /**
     * @test
     */
    public function default_upcast_config_is_loading(): void
    {
        $this->load();

        $this->assertArrayNotHasKey(AsUpcaster::class, $this->container->getAutoconfiguredAttributes());
    }

    /**
     * @test
     */
    public function default_configs_is_loading(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias(UuidEncoder::class);
        $this->assertContainerBuilderHasAlias(ClassNameInflector::class);
        $this->assertContainerBuilderHasAlias(PayloadSerializer::class);
        $this->assertContainerBuilderHasService(TableNameSuffix::class);
        $this->assertContainerBuilderHasService(GenerateAggregateMigrationCommand::class);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
