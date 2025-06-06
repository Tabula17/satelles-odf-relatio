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
        $this->exporterName = $exporterName ?? 'ExportToMail' . uniqid('', false);
    }

    /**
     * Processes the given file by optionally converting it and attaching it to an email.
     *
     * @param string $file
     * @param array|null $parameters
     * @return mixed The result of sending the email after the file is processed and attached.
     * @throws ConversionException If the file conversion fails.
     * @throws ExporterException
     */
    public function processFile(string $file, ?array $parameters = []): mixed
    {
        $filename = $this->filename ?? basename($file);
        if ($this->converter) {
            try {
                $file = $this->converter->convert($file, $filename) ?? $file;
            } catch (Exception $e) {
                throw new ExporterException(sprintf(ExporterException::DEFAULT_MESSAGE, $e->getMessage()));
            }
        }
        $this->mail->attach($file, $filename);
        return $this->mail->send();
    }
}