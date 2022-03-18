<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AclMessageFilterChain
{
    public function __construct(
        public readonly string $beforeTranslate = 'match_all',
        public readonly string $afterTranslate = 'match_all',
    ) {
    }
}
