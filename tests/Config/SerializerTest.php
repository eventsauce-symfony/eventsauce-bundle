<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Tests\Dummy\DummyMessageSerializer;
use Tests\Dummy\DummyPayloadSerializer;
use Tests\Dummy\DummySnapshotStateSerializer;

final class SerializerTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_serializers(): void
    {
        $this->load([
            'snapshot' => [
                'repository' => [
                    'doctrine' => true,
                ],
            ],
            'serializer' => [
                'payload' => DummyPayloadSerializer::class,
                'message' => DummyMessageSerializer::class,
                'snapshot' => DummySnapshotStateSerializer::class,
            ],
        ]);

        $this->container->register(DummyPayloadSerializer::class, DummyPayloadSerializer::class);
        $this->container->register(DummyMessageSerializer::class, DummyMessageSerializer::class);
        $this->container->register(DummySnapshotStateSerializer::class, DummySnapshotStateSerializer::class);

        $this->assertContainerBuilderHasAlias(PayloadSerializer::class);
        $payloadDefinition = $this->container->findDefinition(PayloadSerializer::class);
        $this->assertEquals(DummyPayloadSerializer::class, $payloadDefinition->getClass());

        $this->assertContainerBuilderHasAlias(MessageSerializer::class);
        $messageDefinition = $this->container->findDefinition(MessageSerializer::class);
        $this->assertEquals(DummyMessageSerializer::class, $messageDefinition->getClass());

        $this->assertContainerBuilderHasAlias(SnapshotStateSerializer::class);
        $messageDefinition = $this->container->findDefinition(SnapshotStateSerializer::class);
        $this->assertEquals(DummySnapshotStateSerializer::class, $messageDefinition->getClass());
    }

    /**
     * @test
     */
    public function should_not_load_snapshot_serializer_if_snapshot_config_is_disabled(): void
    {
        $this->load([
            'snapshot' => false,
            'serializer' => [
                'snapshot' => DummySnapshotStateSerializer::class,
            ],
        ]);

        $this->assertContainerBuilderNotHasService(SnapshotStateSerializer::class);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
