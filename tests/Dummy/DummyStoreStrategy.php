<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauce\Snapshotting\CanStoreSnapshotStrategy;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;

final class DummyStoreStrategy implements CanStoreSnapshotStrategy
{
    public function canStore(AggregateRootWithSnapshotting $aggregateRoot): bool
    {
    }
}
