<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMessageDecorator
{
    public function __construct(
        public int $priority = 0,
    ) {
    }
}
