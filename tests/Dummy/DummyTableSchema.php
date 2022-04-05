<?php
declare(strict_types=1);

namespace Tests\Dummy;

use EventSauce\MessageRepository\TableSchema\TableSchema;

class DummyTableSchema implements TableSchema
{
    public function eventIdColumn(): string
    {
    }

    public function aggregateRootIdColumn(): string
    {
    }

    public function versionColumn(): string
    {
    }

    public function payloadColumn(): string
    {
    }

    public function additionalColumns(): array
    {
    }
}