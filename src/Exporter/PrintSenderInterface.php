<?php

namespace Tabula17\Satelles\Odf\Exporter;

/**
 *
 */
interface PrintSenderInterface
{
    public function print(string $file): mixed;
}