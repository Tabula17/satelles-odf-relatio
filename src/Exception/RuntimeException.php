<?php

namespace Tabula17\Satelles\Odf\Exception;

class RuntimeException extends RelatioException
{
    public const string DEFAULT_MESSAGE = 'A runtime error has occurred.';
    public const string FAILED_TO_LOAD = 'Failed to load file "%s".';
    public const string ACTION_ERROR = 'Error while %s "%s"';
    public const string ACTION_ERROR_WITH_OUTPUT = 'Error while %s "%s": %s';
}
