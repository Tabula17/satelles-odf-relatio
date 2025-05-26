<?php

namespace Tabula17\Satelles\Odf\Exception;

class ValidationException extends RelatioException
{
    public const string DEFAULT_MESSAGE = 'Validation error.';
    public const string INVALID_PATH = 'The path "%s" is not valid.';
    public const string EMPTY_PATH = 'The provided path cannot be empty.';
    public const string EMPTY_NULL_FIELD = '%s cannot be null or empty.';
    public const string NULL_FIELD = '%s cannot be null.';
    public const string EMPTY_FIELD = '%s cannot be empty.';
    public const string EMPTY_STRING = '%s cannot be an empty string.';
    public const string EMPTY_KEY = '"%s" cannot be empty.';
    public const string ONLY_STRING_OR_NULL = 'The value of "%s" must be a string or null, "%s" provided.';
    public const string VALUE_NOT_SET = 'The value of "%s" is not set.';
    public const string ACTION_BEFORE_ACTION = '%s must %s before %s.';
    public const string INVALID_TYPE = 'The data type "%s" is not valid.';
    public const string INVALID_VALUE = 'The value "%s" is not valid.';
    public const string MISSING_REQUIRED_FIELD = 'The field "%s" is required and has not been provided.';
    public const string INVALID_FORMAT = 'The format of the value "%s" is not valid.';
    public const string OUT_OF_RANGE = 'The value "%s" is out of the allowed range.';
    public const string UNEXPECTED_TYPE = 'The data type "%s" is not as expected.';
    public const string DUPLICATE_ENTRY = 'An entry with the value "%s" already exists.';
    public const string INVALID_EMAIL = 'The email "%s" is not valid.';
    public const string INVALID_URL = 'The URL "%s" is not valid.';
    public const string INVALID_DATE = 'The date "%s" is not valid.';
    public const string INVALID_TIME = 'The time "%s" is not valid.';
    public const string INVALID_DATE_TIME = 'The date and time "%s" are not valid.';
    public const string INVALID_JSON = 'The provided JSON is not valid.';
    public const string INVALID_XML = 'The provided XML is not valid.';
    public const string INVALID_CSV = 'The provided CSV is not valid.';
    public const string INVALID_NUMBER = 'The number "%s" is not valid.';
    public const string INVALID_BOOLEAN = 'The boolean value "%s" is not valid.';
    public const string INVALID_ENUM = 'The value "%s" is not a valid member for "%s".';
    public const string INVALID_LENGTH = 'The length of the value "%s" is not valid.';
    public const string INVALID_PATTERN = 'The value "%s" does not match the expected pattern.';
    public const string INVALID_DIR = 'The directory "%s" is not valid.';
    public const string IS_NOT_DIR =  'The value "%s" is not a valid directory.';
    public const string EXISTS_BUT_NOT_DIR = 'The path "%s" exists but is not a valid directory.';
    public const string INVALID_FILE_TYPE = 'The file type "%s" is not valid.';
    public const string INVALID_FILE_SIZE = 'The file size "%s" is not valid.';
    public const string INVALID_FILE_EXTENSION = 'The file extension "%s" is not valid.';
    public const string INVALID_IP_ADDRESS = 'The IP address "%s" is not valid.';
    public const string INVALID_MAC_ADDRESS = 'The MAC address "%s" is not valid.';
    public const string INVALID_UUID = 'The UUID "%s" is not valid.';
    public const string INVALID_COLOR = 'The color "%s" is not valid.';
    public const string INVALID_CURRENCY = 'The currency "%s" is not valid.';
    public const string INVALID_PHONE_NUMBER = 'The phone number "%s" is not valid.';
    public const string INVALID_POSTAL_CODE = 'The postal code "%s" is not valid.';
    public const string INVALID_COUNTRY_CODE = 'The country code "%s" is not valid.';
    public const string INVALID_LANGUAGE_CODE = 'The language code "%s" is not valid.';
    public const string CONTAINS_INVALID_CHARS = '%s contains invalid characters. Only alphanumeric characters and underscores are allowed.';
    public function __construct($message = self::DEFAULT_MESSAGE, $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

