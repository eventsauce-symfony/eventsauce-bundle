<?php

declare(strict_types=1);

namespace Tests\Factory\Dummy;

use EventSauce\EventSourcing\Upcasting\Upcaster;

final class DummyUpcaster implements Upcaster
{
    public function upcast(array $message): array
    {
    }
}
