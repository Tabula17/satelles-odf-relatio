<?php

namespace Tabula17\Satelles\Odf;

/**
 *
 */
interface ConverterInterface
{
    /**
     * @param string $file
     * @param string|null $outputName
     * @return string|null
     */
    public function convert(string $file, ?string $outputName = null): ?string;
}