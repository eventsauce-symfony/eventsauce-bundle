<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\Doubles;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\SnapshottingBehaviour;

class DummyFooAggregateWithSnapshotting implements AggregateRootWithSnapshotting
{
    use AggregateRootBehaviour;
    use SnapshottingBehaviour;

    protected function createSnapshotState(): mixed
    {
    }

    protected static function reconstituteFromSnapshotState(AggregateRootId $id, $state): AggregateRootWithSnapshotting
    {
    }
}
