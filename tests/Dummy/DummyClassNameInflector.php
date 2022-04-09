<?php

declare(strict_types=1);

namespace Tests\Dummy;

use EventSauce\EventSourcing\ClassNameInflector;

class DummyClassNameInflector implements ClassNameInflector
{
    public function classNameToType(string $className): string
    {
    }

    public function typeToClassName(string $eventType): string
    {
    }

    public function instanceToType(object $instance): string
    {
    }
}
