<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use EventSauce\EventSourcing\Upcasting\Upcaster;
use EventSauce\EventSourcing\Upcasting\UpcasterChain;

final class UpcasterChainFactory
{
    /**
     * @param iterable<Upcaster> $upcasters
     */
    public static function create(iterable $upcasters): Upcaster
    {
        return new UpcasterChain(...$upcasters);
    }
}
