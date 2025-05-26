<?php

namespace Tabula17\Satelles\Odf\Exception;

class XmlProcessException extends FileException
{
    public const string DEFAULT_MESSAGE = 'An error occurred while processing the XML: %s';
    public const string PARSE_ERROR = 'Error parsing XML: %s';
    public const string VALIDATION_ERROR = 'XML validation error: %s';
    public const string EMPTY_PARSE_ERROR = 'Could not parse XML "%s": content is empty or invalid.';
    public const string FAILED_TO_OPEN_FILE = 'Failed to load file "%s".';
    public const string FAILED_TO_PARSE_XML = 'Could not parse XML: %s';
    public const string XML_NOT_LOADED = 'The XML has not been loaded correctly. Make sure the file exists and is accessible.';
}

