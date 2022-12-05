<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Upcasting\MessageUpcaster\MessageUpcaster;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final readonly class UpcasterLoader
{
    public static function load(AndreoEventSauceExtension $extension, ContainerBuilder $container, array $config): void
    {
        $upcastConfig = $config['upcaster'];
        if (!$extension->isConfigEnabled($container, $upcastConfig)) {
            return;
        }

        $trigger = $upcastConfig['trigger'];
        if ('after_unserialize' === $trigger && !interface_exists(MessageUpcaster::class)) {
            throw new LogicException('Message upcaster is not available. Try running "composer require andreo/eventsauce-upcasting".');
        }

        $container->registerAttributeForAutoconfiguration(
            AsUpcaster::class,
            static function (ChildDefinition $definition, AsUpcaster $attribute): void {
                $definition->addTag(
                    'andreo.eventsauce.upcaster',
                    [
                        'version' => $attribute->version,
                        'class' => $attribute->aggregateClass,
                    ]
                );
            }
        );
    }
}
