<?php

namespace Tabula17\Satelles\Odf;

/**
 *
 */
interface FunctionsInterface
{
    /**
     * Gets or sets the working directory for temp files if necessary.
     */
    public ?string $workingDir {
        get;
        set;
    }
}