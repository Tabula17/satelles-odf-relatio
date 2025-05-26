<?php

namespace Tabula17\Satelles\Odf\Exception;

class NonWritableFileException extends FileException
{
    public const string DEFAULT_MESSAGE = 'The file or directory is not writable.';
    public const string NON_WRITABLE_DIR = 'Cannot write to directory "%s"';
}

