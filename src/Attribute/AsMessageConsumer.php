<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsMessageConsumer
{
    public function __construct(
        public string $dispatcher,
    ) {
    }
}
