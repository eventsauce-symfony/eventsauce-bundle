<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

class DummyFooAggregate implements AggregateRoot
{
    use AggregateRootBehaviour;
}
