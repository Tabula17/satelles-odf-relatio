<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Tabula17\Satelles\Odf\Exception\ExporterException;

/**
 *
 */
interface MailSenderInterface
{
    public function setSubject(string $subject): void;

    public function setBody(string $body, string $type = 'text'): void;

    public function setTo(array $to): void;

    public function setFrom(string $from): void;

    public function setCc(array $cc): void;

    public function setBcc(array $bcc): void;

    /**
     * @param string $path
     * @param string|null $filename
     * @return void
     */
    public function attach(string $path, ?string $filename): void;

    /**
     * @return mixed
     * @throws ExporterException
     */
    public function send(): mixed;
}