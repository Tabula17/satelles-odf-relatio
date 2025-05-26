<?php

namespace Tabula17\Satelles\Odf\Exception;

class ConversionException extends RelatioException
{
    public const string DEFAULT_MESSAGE = 'Error during file conversion.';
    public const string FILE_RESULT_NOT_FOUND = 'No se encontró el archivo de resultado de la conversión: %s';
}
