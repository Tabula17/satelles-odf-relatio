<?php

namespace Tabula17\Satelles\Odf;

enum RelatioStatusEnum: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Retrying = 'retrying';
    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled => true,
            default => false
        };
    }

    public function isRunning(): bool
    {
        return match ($this) {
            self::Pending, self::Queued, self::Running, self::Retrying => true,
            default => false
        };
    }

    public function isPending(): bool
    {
        return match ($this) {
            self::Pending, self::Retrying => true,
            default => false
        };
    }
    public function isQueued(): bool
    {
        return match ($this) {
            self::Queued => true,
            default => false
        };
    }
    public function isCancelled(): bool
    {
        return match ($this) {
            self::Cancelled => true,
            default => false
        };
    }
    public function isFailed(): bool
    {
        return match ($this) {
            self::Failed => true,
            default => false
        };
    }
    public function isCompleted(): bool
    {
        return match ($this) {
            self::Completed => true,
            default => false
        };
    }
}
