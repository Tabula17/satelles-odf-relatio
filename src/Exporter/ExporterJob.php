<?php

namespace Tabula17\Satelles\Odf\Exporter;

use DateInterval;
use DateTimeImmutable;
use Tabula17\Satelles\Odf\Converter\ConverterJob;
use Tabula17\Satelles\Odf\Converter\ConverterOutputTypesEnum;
use Tabula17\Satelles\Odf\Exception\RelatioRuntimeException;
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\RelatioStatusEnum;
use Tabula17\Satelles\Utilis\Job\AbstractJob;
use Throwable;

class ExporterJob extends AbstractJob
{
    //public readonly string $jobId;
    public array $data = [];
    public RelatioStatusEnum $status = RelatioStatusEnum::Pending {
        /**
         * @throws RelatioRuntimeException
         */
        set {
            if ($this->status->isFinished()) {
                throw new RelatioRuntimeException('Cannot change status of finished job');
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
                    $this->durationMs = self::dateIntervalToMs($this->startedAt->diff($value));
                }
            }
        }
    public ?float $durationMs = null;
    protected(set) ?ConverterOutputTypesEnum $outputType = null;
    private int $attempts = 0;

    public function __construct(
        public readonly string              $exportId,
        public readonly string              $exporterName,
        string                              $jobId,
        public readonly ExporterActionsEnum $action,
        public readonly string              $file,
        public ?string                      $output = null,
        ?DateTimeImmutable                  $startedAt = null,
        public ?string                      $error = null,
        RelatioStatusEnum                   $status = RelatioStatusEnum::Pending,
        public int                          $maxAttempts = 3,
        public ?int                         $priority = null
    )
    {
        $this->jobId = $jobId;
        $this->status = $status;
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

    private static function dateIntervalToMs(DateInterval $interval): int
    {
        $reference = new DateTimeImmutable('@0'); // Epoch base time
        $endTime = $reference->add($interval);

        // Extract total seconds difference and convert to milliseconds
        $seconds = $endTime->getTimestamp() - $reference->getTimestamp();
        $milliseconds = $seconds * 1000;

        // Add fractional microsecond differences converted to milliseconds
        $milliseconds += (int)($interval->f * 1000);

        return $milliseconds;
    }

    public function cancel(): void
    {
        $this->markCancelled();
    }

    public function canRetry(): bool
    {
        return $this->status->canRetry() && $this->attempts < $this->maxAttempts;
    }

    public function withPriority(int $priority): static
    {
        return clone($this, [
            "priority" => $priority
        ]);
    }

    public function withMaxAttempts(int $maxAttempts): static
    {
        return clone($this, [
            "maxAttempts" => $maxAttempts
        ]);

    }

    public function getStatus(): mixed
    {
        return $this->status;
    }

    public function validate(): void
    {
        // TODO: Implement validate() method.
    }
}