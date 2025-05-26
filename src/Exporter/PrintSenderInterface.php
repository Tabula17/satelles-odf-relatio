<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Tabula17\Satelles\Odf\Exception\ExporterException;

/**
 * Interface for sending print jobs.
 */
interface PrintSenderInterface
{
    /**
     * Prints the specified file.
     *
     * @param string $file The path to the file to be printed.
     *
     * @return mixed The result of the print operation.
     * @throws ExporterException If there is an error during printing.
     */
    public function print(string $file): mixed;
}