<?php

namespace Tabula17\Satelles\Odf\Exporter;

enum ExporterActionsEnum
{
    case Export;
    case Send;
    case Print;
    case Download;
    case Mail;

    public function transformFile():bool
    {
        return $this === self::Export;
    }
}
