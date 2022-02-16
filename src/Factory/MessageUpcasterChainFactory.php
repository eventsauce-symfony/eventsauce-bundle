<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use Andreo\EventSauce\Upcasting\MessageUpcaster;
use Andreo\EventSauce\Upcasting\MessageUpcasterChain;

final class MessageUpcasterChainFactory
{
    /**
     * @param iterable<MessageUpcaster> $upcasters
     */
    public static function create(iterable $upcasters): MessageUpcaster
    {
        return new MessageUpcasterChain(...$upcasters);
    }
}
