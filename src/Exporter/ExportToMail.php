<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Exception;
use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\ExporterInterface;

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
    private ?ConverterInterface $converter;
    private ?string $filename;
    private MailSenderInterface $mail;

    /**
     * @param MailSenderInterface $mail
     * @param string|null $filename
     * @param ConverterInterface|null $converter
     * @param string|null $exporterName
     */
    public function __construct(MailSenderInterface $mail, ?string $filename = null, ?ConverterInterface $converter = null, ?string $exporterName = null)
    {
        $this->filename = $filename;
        $this->converter = $converter;
        $this->mail = $mail;
        $this->exporterName = $exporterName ?? 'ExportToMail'.uniqid('', false);
    }

    /**
     * Processes the given file by optionally converting it and attaching it to an email.
     *
     * @param string $file The file to be processed.
     * @return mixed The result of sending the email after the file is processed and attached.
     * @throws ConversionException If the file conversion fails.
     */
    public function processFile(string $file): mixed
    {
        $filename = $this->filename ?? basename($file);
        if ($this->converter) {
            try {
                $file = $this->converter->convert($file, $filename) ?? $file;
            } catch (Exception $e) {
                throw new ConversionException($e->getMessage());
            }
        }
        $this->mail->attach($file, $filename);
        return $this->mail->send();
    }
}