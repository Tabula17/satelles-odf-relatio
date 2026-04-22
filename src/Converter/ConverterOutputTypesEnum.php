<?php

namespace Tabula17\Satelles\Odf\Converter;

enum ConverterOutputTypesEnum
{
    case Path;
    case FileContent;
    case QueueInfo;
    case ResultInfo;
    case Unchanged;

    public function isFile(): bool
    {
        return $this === self::Path || $this === self::FileContent || $this === self::Unchanged;
    }

    public function inMemory(): bool
    {
        return $this === self::FileContent;
    }

    public function isFinalResult(): bool
    {
        return $this === self::ResultInfo || $this->isFile();
    }

    public static function fromString(string $type): self
    {
        return match ($type) {
            'path' => self::Path,
            'file_content' => self::FileContent,
            'queue_info' => self::QueueInfo,
            'result_info' => self::ResultInfo,
            default => self::Unchanged,
        };
    }

}
