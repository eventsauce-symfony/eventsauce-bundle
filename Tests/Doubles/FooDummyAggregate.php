<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\Doubles;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

class FooDummyAggregate implements AggregateRoot
{
    use AggregateRootBehaviour;
}
