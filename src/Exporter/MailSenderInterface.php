<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Tabula17\Satelles\Odf\Exception\ExporterException;

/**
 *
 */
interface MailSenderInterface
{
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