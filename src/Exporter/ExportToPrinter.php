<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Exception;
use Tabula17\Satelles\Odf\ExporterInterface;

/**
 *
 */
class ExportToPrinter implements ExporterInterface
{
    public string $exporterName {
        /**
         * @return string
         */
        get {
            return $this->exporterName;
        }
        /**
         * @return void
         */
        set {
            $this->exporterName = $value;
        }
    }
    public PrintSenderInterface $printer;

    /**
     * @param PrintSenderInterface $printer
     * @param string|null $exporterName
     */
    public function __construct(PrintSenderInterface $printer, ?string $exporterName = null)
    {
        $this->printer = $printer;
        $this->exporterName = $exporterName ?? 'ExportToPrinter'.uniqid('', false);
    }


    /**
     * @param string $file
     * @param array|null $parameters
     * @return mixed
     */
    public function processFile(string $file, ?array $parameters = []): mixed
    {
        try {
            return $this->printer->print($file);
        }catch (Exception $e) {
            return false;
        }
    }
}