<?php

namespace Tabula17\Satelles\Odf\Converter;

use DateTimeImmutable;
use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Odf\Exporter\ExporterActionsEnum;
use Tabula17\Satelles\Odf\RelatioStatusEnum;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
use Throwable;

class ConverterJob extends AbstractDescriptor
{
    public readonly string $convertId;
    public readonly string $exportId;
    public readonly string $jobId;
    protected(set) ConverterOutputTypesEnum $outputType;
    public int $attempts = 0;
    public readonly string $file;
    public ?string $output = null;
    public array $options = [];
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

    public function __construct(
        string                   $convertId,
        string                   $exportId,
        string                   $jobId,
        ConverterOutputTypesEnum $outputType,
        string                   $file,
        array                    $options = [],
        ?string                  $output = null,
        ?DateTimeImmutable       $startedAt = null,
        ?string                  $error = null,
        RelatioStatusEnum        $status = RelatioStatusEnum::Pending
    )
    {
        $this->convertId = $convertId;
        $this->exportId = $exportId;
        $this->jobId = $jobId;
        $this->outputType = $outputType;
        $this->file = $file;
        $this->options = $options;
        $this->output = $output;
        $this->status = $status;
        $this->error = $error;
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
        parent::__construct();
    }

    public function jobResult(): array|null
    {
        if ($this->status->isFinished()) {
            $data = $this->data;
            $data['jobId'] = $this->exportId;
            $data['conversionId'] = $this->convertId;
            $data['file'] = $this->output ?? $this->file;
            $data['stats'] = [
                'durationMs' => $this->durationMs,
                'startedAt' => $this->startedAt->format(DATE_ATOM),
                'finishedAt' => $this->finishedAt->format(DATE_ATOM),
                'status' => $this->status->value,
            ];
            if ($this->status->isFailed()) {
                $data['error'] = $this->error ?? 'Unknown error when processing conversion job';
            }
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

    public function isQueued(): bool
    {
        return $this->status === RelatioStatusEnum::Queued;
    }

    public function isRunning(): bool
    {
        return $this->status === RelatioStatusEnum::Running;
    }

    public function isCompleted(): bool
    {
        return $this->status === RelatioStatusEnum::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === RelatioStatusEnum::Failed;
    }

    public function isCancelled(): bool
    {
        return $this->status === RelatioStatusEnum::Cancelled;
    }

    public function isRetrying(): bool
    {
        return $this->status === RelatioStatusEnum::Retrying;
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function switchTo(ConverterOutputTypesEnum $outputType): void
    {
        $this->outputType = $outputType;
    }
}