<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsUpcaster
{
    /**
     * @param class-string $aggregateClass
     */
    public function __construct(
        public string $aggregateClass,
        public int $version
    ) {
    }
}
