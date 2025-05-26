<?php

namespace Tabula17\Satelles\Odf\Functions;

use Exception;
use Tabula17\Satelles\Odf\Exception\FunctionRendererException;
use Tabula17\Satelles\Odf\FunctionsInterface;

/**
 *
 */
class Base implements FunctionsInterface
{
    /**
     * Magic method to handle calls to undefined methods.
     *
     * @param string $func The name of the method being called.
     * @param array $args The arguments passed to the method.
     * @return mixed The result of the dynamically called method.
     * @throws FunctionRendererException
     */
    public function __call(string $func, array $args)
    {
        try {
            return call_user_func_array($func, $args);
        } catch (Exception $e) {
            throw new FunctionRendererException(sprintf(FunctionRendererException::DEFAULT_MESSAGE, $func) . ': ' . $e->getMessage(), 0, $e);
        }
    }

    public null|string $workingDir {
        get {
            return $this->workingDir;
        }
        set {
            $this->workingDir = $value;
        }
    }
}