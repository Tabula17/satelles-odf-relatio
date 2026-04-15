<?php

declare(strict_types=1);

namespace Tabula17\Satelles\Odf\Functions;

use Throwable;
use Tabula17\Satelles\Odf\Exception\FunctionRendererException;
use Tabula17\Satelles\Odf\FunctionsInterface;

/**
 * Base class for function handling in ODF templates.
 * Provides magic method support for calling native PHP functions.
 */
class Base implements FunctionsInterface
{

    /**
     * Property Hook for working directory
     */
    public ?string $workingDir;

    /**
     * Constructor
     *
     * @param string|null $workingDir Optional working directory path
     */
    public function __construct(?string $workingDir = null)
    {
        $this->workingDir = $workingDir;
    }

    /**
     * Magic method to handle calls to undefined methods.
     * Allows using native PHP functions as template functions.
     *
     * @param string $func The name of the function being called
     * @param array $args The arguments passed to the function
     * @return mixed The result of the dynamically called function
     * @throws FunctionRendererException If the function doesn't exist or fails
     */
    public function __call(string $func, array $args): mixed
    {
        // Verificar si la función existe y es callable
        if (!function_exists($func)) {
            throw new FunctionRendererException(
                sprintf(FunctionRendererException::DEFAULT_MESSAGE, $func) .
                ': Function does not exist'
            );
        }

        try {
            return $func(...$args); // Usar spread operator (más moderno que call_user_func_array)
        } catch (Throwable $e) {
            throw new FunctionRendererException(
                sprintf(FunctionRendererException::DEFAULT_MESSAGE, $func) . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Checks if a function is available
     *
     * @param string $name Function name
     * @return bool True if the function exists
     */
    public function hasFunction(string $name): bool
    {
        return method_exists($this, $name) || function_exists($name);
    }
}