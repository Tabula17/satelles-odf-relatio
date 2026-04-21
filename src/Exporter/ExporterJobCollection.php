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
       return  array_merge($this->collect('file'), $this->collect('output'));
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(ExporterJobCollection $config) => $config->$key, $this->values));
    }
}