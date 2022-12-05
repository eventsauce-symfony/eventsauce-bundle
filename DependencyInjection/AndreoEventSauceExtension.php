<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection;

use Andreo\EventSauceBundle\DependencyInjection\Loader\AclLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\AggregatesLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\ClockLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\EventDispatcherLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MessageDecoratorLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MessageDispatcherLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MessageOutboxLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MessageStorageLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\MigrationGeneratorLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\SnapshotLoader;
use Andreo\EventSauceBundle\DependencyInjection\Loader\UpcasterLoader;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class AndreoEventSauceExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->getConfiguration([], $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('eventsauce.php');

        ClockLoader::load($container, $config);
        MessageStorageLoader::load($this, $container, $config);
        AclLoader::load($this, $container, $config);
        MessageDecoratorLoader::load($this, $container, $config);
        MessageDispatcherLoader::load($this, $container, $config);
        EventDispatcherLoader::load($this, $container, $config);
        UpcasterLoader::load($this, $container, $config);
        MessageOutboxLoader::load($this, $container, $loader, $config);
        SnapshotLoader::load($this, $container, $loader, $config);
        MigrationGeneratorLoader::load($this, $container, $loader, $config);
        AggregatesLoader::load($this, $container, $config);
    }

    public function isConfigEnabled(ContainerBuilder $container, array $config): bool
    {
        return parent::isConfigEnabled($container, $config);
    }
}
