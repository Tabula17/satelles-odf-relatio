<?php

namespace Tabula17\Satelles\Odf\Exception;

use Exception;

class RelatioException extends \Exception
{
    /**
     * Creates a new validation exception.
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
/*
class NotFoundException extends RelatioException {}
class PermissionException extends RelatioException {}
class ConfigurationException extends RelatioException {}
class DependencyException extends RelatioException {}
class RendererException extends RelatioException {}
class ExporterException extends RelatioException {}
*/