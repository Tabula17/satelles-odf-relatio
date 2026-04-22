<?php

namespace Tabula17\Satelles\Odf\Exporter;

use DateTimeImmutable;
use Tabula17\Satelles\Odf\Converter\ConverterJob;
use Tabula17\Satelles\Odf\Converter\ConverterOutputTypesEnum;
use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\RelatioStatusEnum;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
use Throwable;

class ExporterJob extends AbstractDescriptor
{
    public readonly string $exportId;
    public readonly string $exporterName;
    public readonly string $jobId;
    public readonly ExporterActionsEnum $action;
    public readonly string $file;
    public ?string $output = null;
    public array $data = [];
    public ?string $error = null;
    public RelatioStatusEnum $status = RelatioStatusEnum::Pending {
        /**
         * @throws RuntimeException
         */
        set {
            if ($this->status->isFinished()) {
                throw new RuntimeException('Cannot change status of finished job');
            }
            $this->status = $value;
            if ($value->isFinished()) {
                $this->finishedAt = new DateTimeImmutable();
            }
        }
    }
    protected(set) DateTimeImmutable $startedAt
        {
            set(DateTimeImmutable|string $value) {
                if (is_string($value)) {
                    try {
                        $this->startedAt = new DateTimeImmutable($value);
                    } catch (Throwable $ignored) {
                        $this->startedAt = new DateTimeImmutable();
                    }
                } else {
                    $this->startedAt = $value;
                }
            }
        }
    protected(set) ?DateTimeImmutable $finishedAt
        {
            set(DateTimeImmutable|string|null $value) {
                if (is_string($value)) {
                    try {
                        $this->finishedAt = new DateTimeImmutable($value);
                    } catch (Throwable $ignored) {
                        $this->finishedAt = new DateTimeImmutable();
                    }
                } else {
                    $this->finishedAt = $value;
                }
                if ($value) {
                    $this->durationMs = $this->startedAt->diff($value)->f * 1000;
                }
            }
        }
    public ?float $durationMs = null;
    protected(set) ?ConverterOutputTypesEnum $outputType = null;

    public function __construct(
        string              $exportId,
        string              $exporterName,
        string              $jobId,
        ExporterActionsEnum $action,
        string              $file,
        ?string             $output = null,
        ?DateTimeImmutable  $startedAt = null,
        ?string             $error = null,
        RelatioStatusEnum   $status = RelatioStatusEnum::Pending
    )
    {
        $this->exportId = $exportId;
        $this->exporterName = $exporterName;
        $this->jobId = $jobId;
        $this->action = $action;
        $this->file = $file;
        $this->output = $output;
        $this->status = $status;
        $this->error = $error;
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
        $this->outputType = ConverterOutputTypesEnum::Unchanged;
        parent::__construct();
    }

    public function jobResult(): array|null
    {
        if ($this->status->isFinished()) {
            $data = $this->data;
            $data['jobId'] = $this->exportId;
            $data['file'] = $this->output ?? $this->file;
            if ($this->status->isFailed()) {
                $data['error'] = $this->error ?? 'Unknown error when processing export job';
            }
            $data['status'] = $this->status->value;
            $data['outputType'] = $this->outputType;
            $data['stats'] = [
                'durationMs' => $this->durationMs,
                'startedAt' => $this->startedAt->format(DATE_ATOM),
                'finishedAt' => $this->finishedAt->format(DATE_ATOM),
            ];

            return $data;
        }
        return null;
    }

    public function markQueued(): void
    {
        $this->status = RelatioStatusEnum::Queued;
    }

    public function markRunning(): void
    {
        $this->status = RelatioStatusEnum::Running;
    }

    public function markCompleted(): void
    {
        $this->status = RelatioStatusEnum::Completed;
    }

    public function markFailed(): void
    {
        $this->status = RelatioStatusEnum::Failed;
    }

    public function markRetrying(): void
    {
        $this->status = RelatioStatusEnum::Retrying;
        $this->attempts++;
    }

    public function markCancelled(): void
    {
        $this->status = RelatioStatusEnum::Cancelled;
    }

    public function switchTo(ConverterOutputTypesEnum $outputType): void
    {
        $this->outputType = $outputType;
    }

    public function getConverterJob(?string $outputTo = null, ConverterOutputTypesEnum $outputType = ConverterOutputTypesEnum::Path): ConverterJob
    {
        return new ConverterJob(
            convertId: OdfProcessor::generateId('convert_'),
            exportId: $this->exportId,
            jobId: $this->jobId,
            outputType: $outputType,
            file: $this->file,
            output: $outputTo ?? $this->output,
        );
    }
}