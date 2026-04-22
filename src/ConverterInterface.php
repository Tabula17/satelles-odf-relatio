<?php

namespace Tabula17\Satelles\Odf;

use Tabula17\Satelles\Odf\Converter\ConverterJob;
use Tabula17\Satelles\Odf\Exporter\ExporterJob;

/**
 *
 */
interface ConverterInterface
{
    /**
     * @param ExporterJob $job
     * @return ConverterJob
     */
    public function convert(ConverterJob $job): ConverterJob;
    //public function convert(string $file, ?string $outputName = null): ?string;
}