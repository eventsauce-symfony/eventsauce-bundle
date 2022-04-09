<?php

declare(strict_types=1);

namespace Tests\Dummy;

use EventSauce\EventSourcing\Upcasting\Upcaster;

class DummyUpcaster implements Upcaster
{
    public function upcast(array $message): array
    {
    }
}
