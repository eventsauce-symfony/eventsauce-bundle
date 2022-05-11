<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class WithOutboundAcl
{
    public function __construct(
        public readonly ?string $filterBeforeStrategy = null,
        public readonly ?string $filterAfterStrategy = null,
    ) {
    }
}
