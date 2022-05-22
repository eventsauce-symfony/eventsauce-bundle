<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection;

use Andreo\EventSauceBundle\DependencyInjection\Loader\AclLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\AggregatesLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\ClassNameInflectorLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\EventDispatcherLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MessageDecoratorLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MessageStorageLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MessengerMessageDispatcherLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MigrationGeneratorLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\OutboxLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\SerializerLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\SnapshotLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\SynchronousMessageDispatcherLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\TimeLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\UpcasterLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\UuidEncoderLoader;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class AndreoEventSauceExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->getConfiguration([], $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('eventsauce.yaml');

        (new TimeLoader($container))($config);
        (new MessageStorageLoader($this, $container))($config);
        (new AclLoader($this, $container))($config);
        (new MessageDecoratorLoader($this, $container))($config);
        (new SynchronousMessageDispatcherLoader($this, $container))($config);
        (new MessengerMessageDispatcherLoader($this, $container))($config);
        (new EventDispatcherLoader($this, $container))($config);
        (new UpcasterLoader($this, $container))($config);
        (new OutboxLoader($this, $loader, $container))($config);
        (new SnapshotLoader($this, $loader, $container))($config);
        (new SerializerLoader($this, $container))($config);
        (new MigrationGeneratorLoader($this, $loader, $container))($config);
        (new UuidEncoderLoader($container))($config);
        (new ClassNameInflectorLoader($container))($config);
        (new AggregatesLoader($this, $container))($config);
    }

    public function isConfigEnabled(ContainerBuilder $container, array $config): bool
    {
        return parent::isConfigEnabled($container, $config);
    }
}
