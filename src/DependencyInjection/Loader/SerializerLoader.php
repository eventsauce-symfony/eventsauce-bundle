<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Snapshotting\ConstructingSnapshotStateSerializer;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class SerializerLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $serializerConfig = $config['serializer'];
        $payloadSerializerId = $serializerConfig['payload'];
        if (!in_array($payloadSerializerId, [null, PayloadSerializer::class, ConstructingPayloadSerializer::class], true)) {
            $this->container->setAlias(PayloadSerializer::class, $payloadSerializerId);
        }

        $messageSerializerServiceId = $serializerConfig['message'];
        if (!in_array($messageSerializerServiceId, [null, MessageSerializer::class, ConstructingMessageSerializer::class], true)) {
            $this->container->setAlias(MessageSerializer::class, $messageSerializerServiceId);
        }

        $snapshotConfig = $config['snapshot'];
        $snapshotRepositoryConfig = $snapshotConfig['repository'];
        if (!$this->extension->isConfigEnabled($this->container, $snapshotRepositoryConfig['doctrine'])) {
            return;
        }

        $snapshotSerializerId = $serializerConfig['snapshot'];
        if (null !== $snapshotSerializerId && !interface_exists(SnapshotStateSerializer::class)) {
            throw new LogicException('Snapshot state serializer is not available. Try running "composer require andreo/eventsauce-snapshotting".');
        }
        if (!in_array($snapshotSerializerId, [null, SnapshotStateSerializer::class, ConstructingSnapshotStateSerializer::class], true)) {
            $this->container->setAlias(SnapshotStateSerializer::class, $snapshotSerializerId);
        }
    }
}
