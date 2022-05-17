<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Andreo\EventSauceBundle\Enum\FilterPosition;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsMessageFilter
{
    public function __construct(
        public readonly FilterPosition $position,
        public readonly int $priority = 0
    ) {
    }
}
