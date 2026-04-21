<?php

namespace Tabula17\Satelles\Odf;

use Tabula17\Satelles\Odf\Exporter\ExporterActionsEnum;
use Tabula17\Satelles\Odf\Exporter\ExporterJob;

/**
 *
 */
interface ExporterInterface
{
    public ExporterActionsEnum $action {
        get;
        set;
    }
    public string $exporterName {
        get;
        set;
    }
    public ?ConverterInterface $converter {
        set;
    }


    /**
     * @param string $file
     * @param array|null $parameters
     * @return mixed
     */
    public function processFile(ExporterJob $job, ?array $parameters = [], ?array $previousFiles = []): ExporterJob;
}