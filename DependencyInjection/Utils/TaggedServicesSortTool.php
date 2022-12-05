<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Utils;

use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class TaggedServicesSortTool
{
    /**
     * @return Reference[]
     */
    public static function findAndSort(
        ContainerBuilder $container,
        string $tagName,
        string $sortAttribute = 'priority',
        bool $asc = false
    ): array {
        $services = [];
        foreach ($container->findTaggedServiceIds($tagName, true) as $serviceId => $attributes) {
            foreach ($attributes as $attribute) {
                $sortAttributeValue = $attribute[$sortAttribute] ?? 0;
                $services[] = [$sortAttributeValue, $serviceId];
            }
        }

        $sortByAttribute = array_column($services, 0);
        $result = array_multisort($sortByAttribute, $asc ? SORT_ASC : SORT_DESC, $services);
        if (!$result) {
            throw new RuntimeException('Sort services problem.');
        }

        $result = [];
        foreach ($services as [, $serviceId]) {
            $result[] = new Reference($serviceId);
        }

        return $result;
    }
}
