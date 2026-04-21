<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class ExporterJobCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return ExporterJob::class;
    }

    public function finished(): bool
    {
        return array_all($this->values, static fn($value) => $value->isFinished());
    }

    public function getFiles(): array
    {
        return array_filter(array_merge(array_values($this->collect('file')), array_values($this->collect('output'))), static fn($value) => file_exists($value));
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(ExporterJob $config) => $config->$key, $this->values));
    }
}