<?php

namespace Tabula17\Satelles\Odf\Exporter;

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
     */
    public function send(): mixed;
}