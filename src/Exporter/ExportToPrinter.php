<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Exception;
use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ExporterException;
use Tabula17\Satelles\Odf\ExporterInterface;
use Tabula17\Satelles\Odf\FunctionsInterface;
use Throwable;

/**
 *
 */
class ExportToPrinter implements ExporterInterface
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
    public PrintSenderInterface $printer;

    public ?ConverterInterface $converter {
        set {
            $this->converter = $value;
        }
    }

    /**
     * @param PrintSenderInterface $printer
     * @param string|null $exporterName
     */
    public function __construct(PrintSenderInterface $printer, ?string $exporterName = null)
    {
        $this->printer = $printer;
        $this->exporterName = $exporterName ?? substr(strrchr('\\' . static::class, '\\'), 1) . '_' . uniqid('', false);
        $this->action = ExporterActionsEnum::Print;
    }


    /**
     * @param ExporterJob $job
     * @param array|null $parameters
     * @param array|null $previousFiles
     * @return mixed
     */
    public function processFile(ExporterJob $job, ?array $parameters = [], ?array $previousFiles = []): ExporterJob
    {
        $job->markRunning();
        // if 'file' is set on parameters or exists file on $previousFiles (can be an early conversion), use it, otherwise use the file from the job
        $file = $parameters['file'] ?? end($previousFiles) ?? $job->file;
        try {
            $job->data = [
                'result' => $this->printer->print($file)
            ];
            $job->markCompleted();
        } catch (Throwable $th) {
            $job->markFailed();
            $job->error = $th->getMessage();
            $job->data = [
                'trace' => $th->getTraceAsString(),
                'file' => $th->getFile(),
            ];
        }
        return $job;
    }
}