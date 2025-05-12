<?php

namespace Tabula17\Satelles\Odf\Functions;

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
     */
    public function __call(string $func, array $args) {
        return call_user_func_array($func, $args);
    }
}