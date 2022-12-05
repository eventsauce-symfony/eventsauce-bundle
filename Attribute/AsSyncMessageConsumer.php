<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSyncMessageConsumer
{
    public function __construct(
        public string $dispatcher,
        public int $priority = 0,
    ) {
    }
}
