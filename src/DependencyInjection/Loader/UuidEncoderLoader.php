<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use EventSauce\UuidEncoding\BinaryUuidEncoder;
use EventSauce\UuidEncoding\UuidEncoder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class UuidEncoderLoader
{
    public function __construct(private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $encoderServiceId = $config['uuid_encoder'];
        if (!in_array($encoderServiceId, [null, UuidEncoder::class, BinaryUuidEncoder::class], true)) {
            $this->container->setAlias(UuidEncoder::class, $encoderServiceId);
        }
    }
}
