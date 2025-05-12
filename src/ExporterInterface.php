<?php

namespace Tabula17\Satelles\Odf;

/**
 *
 */
interface ExporterInterface
{
    public string $exporterName {
        /**
         * @return void
         */
        get;
        /**
         * @return void
         */
        set;
    }

    /**
     * @param string $file
     * @return mixed
     */
    public function processFile(string $file): mixed;
}