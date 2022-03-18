<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ClassNameInflectorLoader
{
    public function __construct(private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $aggregateClassNameInflectorServiceId = $config['class_name_inflector'];
        if (!in_array($aggregateClassNameInflectorServiceId, [null, ClassNameInflector::class, DotSeparatedSnakeCaseInflector::class], true)) {
            $this->container->setAlias(ClassNameInflector::class, $aggregateClassNameInflectorServiceId);
        }
    }
}
