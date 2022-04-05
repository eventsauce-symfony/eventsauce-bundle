<?php

declare(strict_types=1);

namespace Tests\DefaultConfig;

use Andreo\EventSauce\Doctrine\Migration\GenerateAggregateMigrationCommand;
use Andreo\EventSauce\Doctrine\Migration\TableNameSuffix;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauceBundle\Attribute\Acl;
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\Attribute\AsMessageFilterAfter;
use Andreo\EventSauceBundle\Attribute\AsMessageFilterBefore;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use Andreo\EventSauceBundle\Attribute\AsSynchronousMessageConsumer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\BackOff\BackOffStrategy;
use EventSauce\Clock\Clock;
use EventSauce\Clock\SystemClock;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector;
use EventSauce\EventSourcing\MessageDispatchingEventDispatcher;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use EventSauce\UuidEncoding\BinaryUuidEncoder;
use EventSauce\UuidEncoding\UuidEncoder;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\Dummy\DummyMessageDecorator;

final class DefaultConfigExtensionTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_time(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'andreo.eventsauce.time.timezone',
            0,
            'UTC'
        );

        $this->assertContainerBuilderHasAlias(Clock::class);
        $clockDefinition = $this->container->findDefinition(Clock::class);
        $this->assertEquals(SystemClock::class, $clockDefinition->getClass());

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            Clock::class,
            0,
            new Reference('andreo.eventsauce.time.timezone')
        );
    }

    /**
     * @test
     */
    public function should_load_event_store(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias('andreo.eventsauce.doctrine.connection');
        $this->assertContainerBuilderHasAlias(TableSchema::class);

        $tableSchemaDefinition = $this->container->findDefinition(TableSchema::class);
        $this->assertEquals(DefaultTableSchema::class, $tableSchemaDefinition->getClass());
    }

    /**
     * @test
     */
    public function should_not_load_acl(): void
    {
        $this->load();

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayNotHasKey(Acl::class, $attributes);
        $this->assertArrayNotHasKey(AsMessageFilterBefore::class, $attributes);
        $this->assertArrayNotHasKey(AsMessageFilterAfter::class, $attributes);
        $this->assertArrayNotHasKey(AsMessageTranslator::class, $attributes);
    }

    /**
     * @test
     */
    public function should_load_message_decorator(): void
    {
        $this->load();

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(AsMessageDecorator::class, $attributes);
        $this->assertContainerBuilderHasService('andreo.eventsauce.message_decorator_chain');

        $this->container
            ->register(DummyMessageDecorator::class, DummyMessageDecorator::class)
            ->setAutoconfigured(true)
        ;
        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(DummyMessageDecorator::class, 'andreo.eventsauce.aggregate_message_decorator', [
            'priority' => 10
        ]);

        /** @var TaggedIteratorArgument $chainArgument */
        $chainArgument = $this->container->findDefinition('andreo.eventsauce.message_decorator_chain')->getArgument(0);

        $this->assertInstanceOf(TaggedIteratorArgument::class, $chainArgument);
        $this->assertCount(2, $chainArgument->getValues());
    }

    /**
     * @test
     */
    public function should_not_load_message_dispatchers(): void
    {
        $this->load();

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayNotHasKey(AsSynchronousMessageConsumer::class, $attributes);

        $this->assertContainerBuilderNotHasService('andreo.eventsauce.message_dispatcher_chain');
    }

    /**
     * @test
     */
    public function should_not_load_event_dispatcher(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(MessageDispatchingEventDispatcher::class);
        $this->assertContainerBuilderNotHasService(EventDispatcher::class);
    }

    /**
     * @test
     */
    public function should_not_load_outbox(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(BackOffStrategy::class);
    }

    /**
     * @test
     */
    public function should_not_load_snapshot(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(SnapshotStateSerializer::class);
    }

    /**
     * @test
     */
    public function should_load_uuid_encoder(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias(UuidEncoder::class);
        $encoderDefinition = $this->container->findDefinition(UuidEncoder::class);
        $this->assertEquals(BinaryUuidEncoder::class, $encoderDefinition->getClass());
    }

    /**
     * @test
     */
    public function should_load_class_name_inflector(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias(ClassNameInflector::class);
        $encoderDefinition = $this->container->findDefinition(ClassNameInflector::class);
        $this->assertEquals(DotSeparatedSnakeCaseInflector::class, $encoderDefinition->getClass());
    }

    /**
     * @test
     */
    public function should_load_serializer(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias(PayloadSerializer::class);
        $encoderDefinition = $this->container->findDefinition(PayloadSerializer::class);
        $this->assertEquals(ConstructingPayloadSerializer::class, $encoderDefinition->getClass());

        $this->assertContainerBuilderHasAlias(MessageSerializer::class);
        $encoderDefinition = $this->container->findDefinition(MessageSerializer::class);
        $this->assertEquals(ConstructingMessageSerializer::class, $encoderDefinition->getClass());

        $this->assertContainerBuilderNotHasService(SnapshotStateSerializer::class);
    }

    /**
     * @test
     */
    public function should_migration_generator(): void
    {
        $this->load();

        $this->assertContainerBuilderNotHasService(TableNameSuffix::class);
        $this->assertContainerBuilderNotHasService(GenerateAggregateMigrationCommand::class);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
