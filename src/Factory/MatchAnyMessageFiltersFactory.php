<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use EventSauce\EventSourcing\AntiCorruptionLayer\MatchAnyMessageFilter;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;

final class MatchAnyMessageFiltersFactory
{
    /**
     * @param iterable<MessageFilter> $filters
     */
    public static function create(iterable $filters): MessageFilter
    {
        return new MatchAnyMessageFilter(...$filters);
    }
}
