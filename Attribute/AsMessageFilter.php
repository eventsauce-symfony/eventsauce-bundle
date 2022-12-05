<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Andreo\EventSauceBundle\Enum\MessageFilterTrigger;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMessageFilter
{
    /**
     * @param (string|class-string)|array<string|class-string> $owners
     */
    public function __construct(
        public MessageFilterTrigger $trigger = MessageFilterTrigger::BEFORE_TRANSLATE,
        public int $priority = 0,
        public string|array $owners = [],
    ) {
    }
}
