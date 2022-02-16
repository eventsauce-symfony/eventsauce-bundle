<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class AsMessageConsumer
{
    public function __construct(
        public readonly string $dispatcher,
    ) {
    }
}
