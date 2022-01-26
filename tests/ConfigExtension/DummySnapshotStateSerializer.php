<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauce\Snapshotting\SnapshotState;
use Andreo\EventSauce\Snapshotting\SnapshotStateSerializer;

class DummySnapshotStateSerializer implements SnapshotStateSerializer
{
    public function serialize(SnapshotState $state): array
    {
    }

    public function unserialize(array $payload): SnapshotState
    {
    }
}
