<?php

namespace Tabula17\Satelles\Odf;


use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;

/**
 * Class DataRenderer
 *
 * Handles the rendering and processing of structured data with support for arithmetic operations,
 * function application, and strict mode validation.
 */
interface DataRendererInterface
{
    public FunctionsInterface $functions {
        set;
        get;
    }

    public bool $strictMode {
        set;
    }

    public array|null $allData {
        set;
    }

    /**
     * Processes a variable by extracting its components, optionally evaluating
     * arithmetic expressions, applying a function, and validating the result.
     *
     * @param string $tag A string containing the variable tag to be processed,
     *                    which may include an arithmetic expression or function.
     * @param array $data An associative array containing the data that the variable
     *                    tag corresponds to, used for resolving its value.
     *
     * @return mixed Returns the processed value after evaluating the tag and applying
     *               any associated function. Defaults to an empty string if no value
     *               is resolved.
     * @throws StrictValueConstraintException
     */
    public function processVariable(string $tag, array $data): mixed;
}