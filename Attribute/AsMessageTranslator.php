<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMessageTranslator
{
    /**
     * @param (string|class-string)|array<string|class-string> $owners
     */
    public function __construct(
        public int $priority = 0,
        public string|array $owners = [],
    ) {
    }
}
