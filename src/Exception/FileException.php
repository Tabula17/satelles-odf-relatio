<?php

namespace Tabula17\Satelles\Odf\Exception;

class FileException extends RelatioException
{
    public const string DEFAULT_MESSAGE = 'File-related error.';
    public const string IS_NOT_DIRECTORY = 'The file "%s" is not a directory.';
    public const string CANT_READ = 'Could not read the file "%s".';
    public const string CANT_OVERWRITE = 'The directory/file "%s" already exists and a new one could not be created.';
    public const string CANT_CREATE = 'Could not create the directory "%s".';
    public const string CANT_OPEN = 'Could not open the file "%s".';
    public const string CANT_WRITE = 'Could not write to the file "%s".';
    public const string CANT_DELETE = 'Could not delete the file "%s".';
    public const string CANT_RENAME = 'Could not rename the file "%s" to "%s".';
    public const string CANT_MOVE = 'Could not move the file "%s" to "%s".';
    public const string CANT_COPY = 'Could not copy the file "%s" to "%s".';
    public const string CANT_CHMOD = 'Could not change permissions of the file "%s".';
    public const string CANT_CHOWN = 'Could not change owner of the file "%s".';
    public const string CANT_CHGRP = 'Could not change group of the file "%s".';
    public const string CANT_TRUNCATE = 'Could not truncate the file "%s".';
    public const string CANT_LOAD_STREAM = 'Could not load the file stream "%s".';
    public function __construct($message = self::DEFAULT_MESSAGE, $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

