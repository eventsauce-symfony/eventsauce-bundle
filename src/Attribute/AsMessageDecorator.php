<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsMessageDecorator
{
    public function __construct(
        public int $order = 0,
        public MessageContext $context = MessageContext::ALL,
    ) {
    }
}
