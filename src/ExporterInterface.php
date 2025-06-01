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
    public ?ConverterInterface $converter {
        set;
    }


    /**
     * @param string $file
     * @param array|null $parameters
     * @return mixed
     */
    public function processFile(string $file, ?array $parameters = []): mixed;
}