<?php

namespace Tabula17\Satelles\Odf;

/**
 *
 */
interface ExporterInterface
{
    public string $exporterName {
        get;
        set;
    }


    /**
     * @param string $file
     * @return mixed
     */
    public function processFile(string $file): mixed;
}