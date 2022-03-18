<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsMessageFilterAfter
{
    public function __construct(public readonly int $priority = 0)
    {
    }
}
