<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Andreo\EventSauceBundle\Enum\MessageFilterStrategy;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class EnableAcl
{
    public function __construct(
        public MessageFilterStrategy $beforeTranslate = MessageFilterStrategy::MATCH_ALL,
        public MessageFilterStrategy $afterTranslate = MessageFilterStrategy::MATCH_ALL,
    ) {
    }
}
