<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Exception;
use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ExporterException;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\ExporterInterface;
use Tabula17\Satelles\Odf\OdfProcessor;

/**
 *
 */
class ExportToFile implements ExporterInterface
{

    public ExporterActionsEnum $action {
        get {
            return $this->action;
        }
        set {
            $this->action = $value;
        }
    }
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
        $this->exporterName = $exporterName ?? substr(strrchr('\\' . static::class, '\\'), 1) . '_' . uniqid('', false);
        $this->action = ExporterActionsEnum::Export;
    }

    /**
     * Processes the given file by validating its existence, applying a conversion (if applicable),
     * and copying it to the specified destination.
     *
     * @param ExporterJob $job
     * @param array|null $parameters
     * @param array|null $previousFiles
     * @param string $file
     * @return ExporterJob Returns the result of the copy operation or the processed file.
     * @throws ExporterException
     */
    public function processFile(ExporterJob $job, ?array $parameters = [], ?array $previousFiles = []): ExporterJob
    {
        $job->markRunning();
        $file = $job->file;
        try {
            $filename = $this->filename ?? basename($file);

            if (!file_exists($file)) {
                throw new ExporterException(FileNotFoundException::FILE_NOT_FOUND . ': ' . $file);
            }
            if (isset($this->converter)) {
                try {
                    $conversionJob = $job->getConverterJob($filename);
                    $conversionJob->options = $parameters;
                    // $result
                    $job->switchTo($this->converter->convert($conversionJob)->outputType);
                    //$file = $this->converter->convert($file, $filename) ?? $file;
                    if ($conversionJob->isCompleted()) {
                        $file = $conversionJob->output;
                    }
                } catch (Exception $e) {
                    throw new ExporterException(sprintf(ExporterException::DEFAULT_MESSAGE, $e->getMessage()));
                }
            }
            $filePath = $this->path . DIRECTORY_SEPARATOR . $filename;
            if (!copy($file, $filePath)) {
                $error = error_get_last();
                throw new ExporterException(sprintf(FileException::CANT_COPY, $file, $filePath) . ':' . PHP_EOL . $error['message']);
            }
            $job->output = $filePath;
            $job->markCompleted();
        } catch (\Throwable $th) {
            $job->markFailed();
            $job->error = $th->getMessage();
            $job->data = [
                'trace' => $th->getTraceAsString(),
                'file' => $th->getFile(),
            ];
        }
        return $job;
    }

    public function getExporterJob(string $file, string $ownerId): ExporterJob
    {
        return new ExporterJob(
            exportId: OdfProcessor::generateId('fileExport_'),
            exporterName: $this->exporterName,
            jobId: $ownerId,
            action: $this->action,
            file: $file,
        );
    }
}