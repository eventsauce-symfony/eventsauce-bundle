<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Snapshotting\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauce\Snapshotting\CanStoreSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\DoctrineSnapshotRepository;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class SnapshotLoader
{
    public function __construct(
        private AndreoEventSauceExtension $extension,
        private YamlFileLoader $loader,
        private ContainerBuilder $container
    ) {
    }

    public function __invoke(array $config): void
    {
        $snapshotConfig = $config['snapshot'];
        if (!$this->extension->isConfigEnabled($this->container, $snapshotConfig)) {
            return;
        }

        $needLoad = false;

        $snapshotRepositoryConfig = $snapshotConfig['repository'];
        if ($this->extension->isConfigEnabled($this->container, $snapshotRepositoryConfig['doctrine'])) {
            if (!class_exists(DoctrineSnapshotRepository::class)) {
                throw new LogicException('Doctrine snapshot repository is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        $storeStrategyConfig = $snapshotConfig['store_strategy'];
        if ($this->extension->isConfigEnabled($this->container, $storeStrategyConfig['every_n_event'])) {
            if (!interface_exists(CanStoreSnapshotStrategy::class)) {
                throw new LogicException('Store snapshot strategy is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        if ($snapshotConfig['versioned']) {
            if (!class_exists(AggregateRootRepositoryWithVersionedSnapshotting::class)) {
                throw new LogicException('Versioned snapshotting is not available. Try running "composer require andreo/eventsauce-snapshotting".');
            }
            $needLoad = true;
        }

        if ($needLoad) {
            $this->loader->load('snapshot.yaml');
        }
    }
}
