<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use Andreo\EventSauce\Upcasting\MessageUpcaster\MessageUpcaster;
use Andreo\EventSauce\Upcasting\MessageUpcaster\MessageUpcasterChain;

final readonly class MessageUpcasterChainFactory
{
    /**
     * @param iterable<MessageUpcaster> $upcasters
     */
    public static function create(iterable $upcasters): MessageUpcaster
    {
        return new MessageUpcasterChain(...$upcasters);
    }
}
