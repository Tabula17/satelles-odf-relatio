<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Odf\Exporter\PrintSenderInterface;
use Tabula17\Satelles\Utilis\Print\CupsClient;

class SatellesCupsIPPWrapper implements PrintSenderInterface
{
    /**
     * @var mixed|string
     */
    private mixed $printerName;
    private CupsClient $printer;

    public function __construct($host = 'localhost', $port = 631, $printerName = 'default')
    {
        // Initialize connection to CUPS server
        // This is a placeholder; actual implementation would go here
        $this->printerName = $printerName;
        $this->printer = new CupsClient($host, $port);
    }


    /**
     * @inheritDoc
     */
    public function print(string $file): array
    {
        if (!file_exists($file)) {
            throw new RuntimeException("File not found: $file");
        }
        try {
            // Send the file to the printer
            return $this->printer->printJob($this->printerName, file_get_contents($file), "odf-export-printer-" . uniqid('', true), [
                'content-type' => 'application/vnd.oasis.opendocument.text',
                'document-format' => 'odt'
            ]);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to print file: " . $e->getMessage());
        }
    }
}