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
        return array_all($this->values, static fn($value) => $value->status->isFinished());
    }

    public function getFiles(): array
    {
        return array_filter(array_unique(array_merge(array_values($this->collect('file')), array_values($this->collect('output')))), static fn($value) => file_exists($value));
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(ExporterJob $job) => $job->$key, $this->values));
    }

    public function getResults(): array
    {
        return array_filter(array_map(static fn(ExporterJob $job) => $job->jobResult(), $this->values));
    }

    /**
     * Retrieves the output of the last job in the collection that meets the
     * conditions of being finished and having a final result as its output type.
     *
     * @return array|null The job result as an array if a matching job is found,
     *                    or null if no such job exists.
     */
    public function getOutput(): ?array
    {
        /** @var ExporterJob $job */
        foreach ($this->reverse() as $job) {
            if ($job->status->isFinished() && $job->outputType->isFinalResult()) {
                return $job->jobResult();
            }
        }
        return null;
    }
}