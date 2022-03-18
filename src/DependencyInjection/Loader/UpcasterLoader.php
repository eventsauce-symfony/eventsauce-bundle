<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Upcasting\MessageUpcaster;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use LogicException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class UpcasterLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $upcastConfig = $config['upcaster'];
        if (!$this->extension->isConfigEnabled($this->container, $upcastConfig)) {
            return;
        }
        if ('message' === $upcastConfig['argument'] && !interface_exists(MessageUpcaster::class)) {
            throw new LogicException('Message upcaster is not available. Try running "composer require andreo/eventsauce-upcasting".');
        }

        $this->container->registerAttributeForAutoconfiguration(
            AsUpcaster::class,
            static function (ChildDefinition $definition, AsUpcaster $attribute): void {
                $aggregateName = $attribute->aggregate;
                $definition->addTag("andreo.eventsauce.upcaster.$aggregateName", ['priority' => -$attribute->version]);
            }
        );
    }
}
