<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauce\Snapshotting\AggregateRootWithVersionedSnapshotting;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\SnapshottingBehaviour;

class DummyFooAggregateWithVersionedSnapshotting implements AggregateRootWithVersionedSnapshotting
{
    use AggregateRootBehaviour;
    use SnapshottingBehaviour;

    protected function createSnapshotState(): mixed
    {
    }

    protected static function reconstituteFromSnapshotState(AggregateRootId $id, $state): AggregateRootWithSnapshotting
    {
    }

    public static function getSnapshotVersion(): int|string
    {
    }
}
