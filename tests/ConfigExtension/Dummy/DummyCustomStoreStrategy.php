<?php

declare(strict_types=1);

namespace Tests\ConfigExtension\Dummy;

use Andreo\EventSauce\Snapshotting\CanStoreSnapshotStrategy;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;

final class DummyCustomStoreStrategy implements CanStoreSnapshotStrategy
{
    public function canStore(AggregateRootWithSnapshotting $aggregateRoot): bool
    {
    }
}
