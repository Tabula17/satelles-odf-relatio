<?php

namespace Tabula17\Satelles\Odf\Exception;

class FileNotFoundException extends FileException
{
    public const string DEFAULT_MESSAGE = 'File does not exist in the working directory.';
    public const string FILE_NOT_FOUND =  'The file "%s" does not exist.';
}
