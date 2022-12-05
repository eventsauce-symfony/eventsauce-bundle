<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Snapshotting\Conditional\ConditionalSnapshotStrategy;
use Andreo\EventSauce\Snapshotting\Doctrine\DoctrineSnapshotRepository;
use Andreo\EventSauce\Snapshotting\Versioned\AggregateRootRepositoryWithVersionedSnapshotting;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\FileLoader;

final readonly class SnapshotLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        FileLoader $loader,
        array $config
    ): void {
        $snapshotConfig = $config['snapshot'];
        if (!$extension->isConfigEnabled($container, $snapshotConfig)) {
            return;
        }

        $repositoryConfig = $snapshotConfig['repository'];
        $repositoryEnabled = $extension->isConfigEnabled($container, $repositoryConfig);
        $doctrineRepositoryEnabled = $repositoryEnabled && $extension->isConfigEnabled($container, $repositoryConfig['doctrine']);
        if ($doctrineRepositoryEnabled && !class_exists(DoctrineSnapshotRepository::class)) {
            throw new LogicException('Doctrine snapshot repository is not available. Try running "composer require andreo/eventsauce-snapshotting".');
        }

        $conditionalEnabled = $extension->isConfigEnabled($container, $snapshotConfig['conditional']);
        if ($conditionalEnabled && !interface_exists(ConditionalSnapshotStrategy::class)) {
            throw new LogicException('Conditional snapshot strategy is not available. Try running "composer require andreo/eventsauce-snapshotting".');
        }

        $versionedEnabled = $extension->isConfigEnabled($container, $snapshotConfig['versioned']);
        if ($versionedEnabled && !class_exists(AggregateRootRepositoryWithVersionedSnapshotting::class)) {
            throw new LogicException('Versioned snapshotting is not available. Try running "composer require andreo/eventsauce-snapshotting".');
        }

        if ($doctrineRepositoryEnabled || $conditionalEnabled || $versionedEnabled) {
            $loader->load('snapshot.php');
        }
    }
}
