<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Exception;
use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\Exception\ExporterException;
use Tabula17\Satelles\Odf\ExporterInterface;
use Tabula17\Satelles\Odf\FunctionsInterface;

/**
 *
 */
class ExportToMail implements ExporterInterface
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
    private ?string $filename;
    private MailSenderInterface $mail;

    public ?ConverterInterface $converter {
        set {
            $this->converter = $value;
        }
    }

    /**
     * @param MailSenderInterface $mail
     * @param string|null $filename
     * @param ConverterInterface|null $converter
     * @param string|null $exporterName
     */
    public function __construct(MailSenderInterface $mail, ?string $filename = null, ?string $exporterName = null)
    {
        $this->filename = $filename;
        $this->mail = $mail;
        $this->exporterName = $exporterName ?? substr(strrchr('\\' . static::class, '\\'), 1) . '_' . uniqid('', false);
        $this->action = ExporterActionsEnum::Mail;
    }

    /**
     * Processes the given file by optionally converting it and attaching it to an email.
     *
     * @param ExporterJob $job
     * @param array|null $parameters
     * @param array|null $previousFiles
     * @return mixed The result of sending the email after the file is processed and attached.
     */
    public function processFile(ExporterJob $job, ?array $parameters = [], ?array $previousFiles = []): ExporterJob
    {
        $job->markRunning();
        // if 'file' is set on parameters or exists file on $previousFiles (can be an early conversion), use it, otherwise use the file from the job
        $file = $parameters['file'] ?? end($previousFiles) ?? $job->file;
        try {
            $filename = $this->filename ?? basename($file);
            if (isset($this->converter)) {
                try {
                    $file = $this->converter->convert($file, $filename) ?? $file;
                } catch (Exception $e) {
                    throw new ExporterException(sprintf(ExporterException::DEFAULT_MESSAGE, $e->getMessage()));
                }
            }
            $this->mail->attach($file, $filename);

            if (isset($parameters['subject'])) {
                $this->mail->setSubject($parameters['subject']);
            }
            if (isset($parameters['bodyText'])) {
                $this->mail->setBody($parameters['bodyText']);
            }
            if (isset($parameters['bodyHtml'])) {
                $this->mail->setBody($parameters['bodyHtml'], 'html');
            }
            if (isset($parameters['to'])) {
                if (!is_array($parameters['to'])) {
                    $parameters['to'] = [$parameters['to']];
                }
                $this->mail->setTo($parameters['to']);
            }
            if (isset($parameters['from'])) {
                $this->mail->setFrom($parameters['from']);
            }
            if (isset($parameters['cc'])) {
                if (!is_array($parameters['cc'])) {
                    $parameters['cc'] = [$parameters['cc']];
                }
                $this->mail->setCc($parameters['cc']);
            }
            if (isset($parameters['bcc'])) {
                if (!is_array($parameters['bcc'])) {
                    $parameters['bcc'] = [$parameters['bcc']];
                }
                $this->mail->setBcc($parameters['bcc']);
            }

            $job->data = [
                'result' => $this->mail->send()
            ];
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
        //return $this->mail->send();
    }
}