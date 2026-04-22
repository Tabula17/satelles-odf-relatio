<?php

namespace Tabula17\Satelles\Odf\Converter;

enum ConverterOutputTypesEnum
{
    case Path;
    case FileContent;
    case QueueInfo;
    case ResultInfo;

    public function isFile(): bool
    {
        return $this === self::Path || $this === self::FileContent;
    }

    public function inMemory(): bool
    {
        return $this === self::FileContent;
    }

    public function isFinalResult(): bool
    {
        return $this === self::ResultInfo || $this->isFile();
    }

}
