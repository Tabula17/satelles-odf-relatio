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
     * @param ExporterJob $job
     * @param array|null $parameters
     * @param array|null $previousFiles
     * @return mixed
     */
    public function processFile(ExporterJob $job, ?array $parameters = [], ?array $previousFiles = []): ExporterJob;

    /**
     * @param string $file The file to be exported
     * @param string $ownerId The ID process owner of the file
     * @return ExporterJob
     */
    public function getExporterJob(string $file, string $ownerId): ExporterJob;
}