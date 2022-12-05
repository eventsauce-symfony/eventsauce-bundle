<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Utils;

use ReflectionClass;

final readonly class ReflectionTool
{
    /**
     * @param class-string $class
     */
    public static function getLowerStringOfClassShortName(string $class): string
    {
        $aggregateClassReflection = new ReflectionClass($class);

        return strtolower($aggregateClassReflection->getShortName());
    }
}
