<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauce\Outbox\Repository\EventSourcedAggregateRootRepositoryForOutbox;
use Andreo\EventSauce\Snapshotting\Conditional\AggregateRootRepositoryWithConditionalSnapshot;
use Andreo\EventSauce\Snapshotting\Versioned\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Tests\Doubles\DummyFooAggregateWithSnapshotting;
use Andreo\EventSauceBundle\Tests\Doubles\FooDummyAggregate;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\Snapshotting\ConstructingAggregateRootRepositoryWithSnapshotting;
use EventSauce\MessageOutbox\DoctrineOutbox\DoctrineTransactionalMessageRepository;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

final class AggregatesConfigTest extends AbstractExtensionTestCase
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
    public function should_load_aggregate_repository(): void
    {
        $this->load([
            'aggregates' => [
                'foo' => [
                    'class' => FooDummyAggregate::class,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.foo');
        $definition = $this->container->getDefinition('andreo.eventsauce.message_repository.foo');
        $this->assertEquals(DoctrineUuidV4MessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasAlias('fooRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_root_repository.foo');
        $this->assertEquals(EventSourcedAggregateRootRepository::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_event_sourced_outbox_repository_of_doctrine(): void
    {
        $this->load([
            'message_outbox' => [
                'enabled' => true,
            ],
            'aggregates' => [
                'bar' => [
                    'class' => FooDummyAggregate::class,
                    'message_outbox' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('andreo.eventsauce.message_repository.bar');
        $definition = $this->container->getDefinition('andreo.eventsauce.message_repository.bar');
        $this->assertEquals(DoctrineTransactionalMessageRepository::class, $definition->getClass());

        $this->assertContainerBuilderHasAlias('barRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_root_repository.bar');
        $this->assertEquals(EventSourcedAggregateRootRepositoryForOutbox::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_outbox_is_enabled_but_root_outbox_option_is_disabled(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'message_outbox' => false,
            'aggregates' => [
                'qux' => [
                    'class' => FooDummyAggregate::class,
                    'message_outbox' => true,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_register_snapshot_aggregate_repository(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
            ],
            'aggregates' => [
                'baz' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'snapshot' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('bazRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_root_repository.baz');
        $this->assertEquals(ConstructingAggregateRootRepositoryWithSnapshotting::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_doctrine_snapshot_aggregate_repository(): void
    {
        $this->load([
            'snapshot' => [
                'enabled' => true,
                'repository' => [
                    'doctrine' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'snapshot' => true,
                ],
            ],
        ]);

        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_root_repository.foo');
        /** @var Reference $snapshotRepositoryRef */
        $snapshotRepositoryRef = $repositoryDef->getArgument(2);
        $this->assertEquals('andreo.eventsauce.snapshot_repository.foo', $snapshotRepositoryRef->__toString());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_snapshot_is_enabled_but_root_snapshot_option_is_disabled(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'snapshot' => [
                'enabled' => false,
            ],
            'aggregates' => [
                'bar' => [
                    'class' => FooDummyAggregate::class,
                    'snapshot' => true,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_register_versioned_snapshot_aggregate_repository(): void
    {
        $this->load([
            'snapshot' => [
                'versioned' => true,
            ],
            'aggregates' => [
                'foo' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'repository_alias' => 'fooVerRepository',
                    'snapshot' => true,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('fooVerRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_root_repository.foo');
        $this->assertEquals(AggregateRootRepositoryWithVersionedSnapshotting::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_register_snapshot_aggregate_repository_with_conditional_strategy(): void
    {
        $this->load([
            'snapshot' => true,
            'aggregates' => [
                'bar' => [
                    'class' => DummyFooAggregateWithSnapshotting::class,
                    'snapshot' => [
                        'conditional' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasAlias('barRepository');
        $repositoryDef = $this->container->getDefinition('andreo.eventsauce.aggregate_root_repository.bar');
        $this->assertEquals(AggregateRootRepositoryWithConditionalSnapshot::class, $repositoryDef->getClass());
    }

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_upcaster_is_enabled_but_root_upcaster_option_is_disabled(): void
    {
        $this->expectException(LogicException::class);
        $this->load([
            'upcaster' => [
                'enabled' => false,
            ],
            'aggregates' => [
                'foo' => [
                    'class' => FooDummyAggregate::class,
                    'upcaster' => true,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function should_throw_exception_if_aggregate_name_is_not_string(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([
            'aggregates' => [
                0 => [
                    'class' => FooDummyAggregate::class,
                ],
            ],
        ]);
    }
}
