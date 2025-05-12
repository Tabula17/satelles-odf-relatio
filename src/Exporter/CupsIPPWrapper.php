<?php
namespace Tabula17\Satelles\Odf\Exporter;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Smalot\Cups\Builder\Builder;
use Smalot\Cups\CupsException;
use Smalot\Cups\Manager\JobManager;
use Smalot\Cups\Manager\PrinterManager;
use Smalot\Cups\Model\Job;
use Smalot\Cups\Transport\Client;
use Smalot\Cups\Transport\ResponseParser;

/**
 *
 */
class CupsIPPWrapper implements PrintSenderInterface
{
    public PrinterManager $printerManager;
    private JobManager $jobManager;
    public Job $job;
    private string $printer;

    /**
     * @param string $printerName
     * @param Builder|null $builder
     * @param Client|null $client
     * @param ResponseParser|null $responseParser
     */
    public function __construct(
        string $printerName,
        ?Builder $builder = null,
        ?Client $client = null,
        ?ResponseParser $responseParser = null
    ) {
        $this->printer = $printerName;
        $builder = $builder ?? new Builder();
        $client = $client ?? new Client();
        $responseParser = $responseParser ?? new ResponseParser();

        $this->printerManager = new PrinterManager($builder, $client, $responseParser);
        $this->jobManager = new JobManager($builder, $client, $responseParser);
        $this->job = new Job();
    }

    /**
     * Sends a print job to the configured printer.
     *
     * @param string $file The file to be printed.
     * @return mixed The result of the print job submission.
     * @throws ClientExceptionInterface
     * @throws CupsException
     */
    public function print(string $file): mixed
    {
        $printers = $this->printerManager->findByName($this->printer);

        if (empty($printers)) {
            throw new RuntimeException("No se encontró la impresora: {$this->printer}");
        }

        $printer = $printers[0];
        $this->job->addFile($file);

        return $this->jobManager->send($printer, $this->job);
    }
}