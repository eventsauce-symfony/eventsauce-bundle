<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use EventSauce\EventSourcing\AntiCorruptionLayer\MatchAllMessageFilters;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;

final readonly class MatchAllMessageFiltersFactory
{
    /**
     * @param iterable<MessageFilter> $filters
     */
    public static function create(iterable $filters): MessageFilter
    {
        return new MatchAllMessageFilters(...$filters);
    }
}
