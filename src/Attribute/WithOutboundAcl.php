<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Andreo\EventSauceBundle\Enum\FilterStrategy;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class WithOutboundAcl
{
    public function __construct(
        public readonly ?FilterStrategy $beforeStrategy = null,
        public readonly ?FilterStrategy $afterStrategy = null,
    ) {
    }
}
