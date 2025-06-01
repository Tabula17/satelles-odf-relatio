<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Exception;
use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\Exception\ExporterException;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\ExporterInterface;
use Tabula17\Satelles\Odf\FunctionsInterface;

/**
 *
 */
class ExportToFile implements ExporterInterface
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

    public ?ConverterInterface $converter {
        set {
            $this->converter = $value;
        }
    }
    private ?string $filename;
    private string $path;

    /**
     * @param string $path
     * @param string|null $filename
     * @param ConverterInterface|null $converter
     * @param string|null $exporterName
     */
    public function __construct(string $path, ?string $filename = null, ?string $exporterName = null)
    {
        $this->path = $path;
        $this->filename = $filename;
        $this->exporterName = $exporterName ?? 'ExportToFile' . uniqid('', false);
    }

    /**
     * Processes the given file by validating its existence, applying a conversion (if applicable),
     * and copying it to the specified destination.
     *
     * @param string $file
     * @param array|null $parameters
     * @return string Returns the result of the copy operation or the processed file.
     * @throws ExporterException
     */
    public function processFile(string $file, ?array $parameters = []): string
    {
        $filename = $this->filename ?? basename($file);

        if (!file_exists($file)) {
            throw new ExporterException(FileNotFoundException::FILE_NOT_FOUND . ': ' . $file);
        }
        if ($this->converter) {
            try {
                $file = $this->converter->convert($file, $filename) ?? $file;
            } catch (Exception $e) {
                throw new ExporterException(sprintf(ExporterException::DEFAULT_MESSAGE, $e->getMessage()));
            }
        }
        $filePath = $this->path . DIRECTORY_SEPARATOR . $filename;
        if (!copy($file, $filePath)) {
            $error = error_get_last();
            throw new ExporterException(sprintf(FileException::CANT_COPY, $file, $filePath) . ':' . PHP_EOL . $error['message']);
        }
        return $filePath;
    }

}