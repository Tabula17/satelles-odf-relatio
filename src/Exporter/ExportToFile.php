<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Exception;
use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\ExporterInterface;

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
    private ?ConverterInterface $converter;
    private ?string $filename;
    private string $path;

    /**
     * @param string $path
     * @param string|null $filename
     * @param ConverterInterface|null $converter
     * @param string|null $exporterName
     */
    public function __construct(string $path, ?string $filename = null, ?ConverterInterface $converter = null, ?string $exporterName = null)
    {
        $this->path = $path;
        $this->filename = $filename;
        $this->converter = $converter;
        $this->exporterName = $exporterName ?? 'ExportToFile'.uniqid('', false);
    }

    /**
     * Processes the given file by validating its existence, applying a conversion (if applicable),
     * and copying it to the specified destination.
     *
     * @param string $file The path to the file to be processed.
     * @return string Returns the result of the copy operation or the processed file.
     * @throws FileNotFoundException If the specified file does not exist.
     * @throws ConversionException If an error occurs during file conversion.
     */
    public function processFile(string $file): string
    {
        $filename = $this->filename ?? basename($file);

        if (!file_exists($file)) {
            throw new FileNotFoundException("The file '$file' does not exist in the working directory.");
        }
        if ($this->converter) {
            try {
                $file = $this->converter->convert($file, $filename) ?? $file;

            } catch (Exception $e) {
                throw new ConversionException($e->getMessage());
            }
        }
        $filePath = $this->path . DIRECTORY_SEPARATOR . $filename;
        copy($file, $filePath);
        return $filePath;
    }

}