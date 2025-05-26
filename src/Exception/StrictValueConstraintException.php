<?php

namespace Tabula17\Satelles\Odf\Exception;

class StrictValueConstraintException extends RelatioException
{
    public const string DEFAULT_MESSAGE = 'Strict mode enabled. Value is not valid ';
    public const string EMPTY_VALUE = 'Strict mode enabled. Value cannot be empty for the field "%s".';
    public const string INVALID_VALUE = 'Strict mode enabled. Value is not valid';
    public const string INVALID_TYPE = 'The value "%s" is not of the expected type "%s". Expected type: %s, but got: %s.';
    public const string INVALID_LENGTH = 'The value "%s" does not meet the length requirements for the field "%s". Expected length: %d, but got: %d.';
    public const string INVALID_FORMAT = 'The value "%s" does not match the expected format for the field "%s". Expected format: %s, but got: %s.';
    public const string INVALID_RANGE = 'The value "%s" is out of the expected range for the field "%s". Expected range: %s, but got: %s.';
    public const string INVALID_ENUM = 'The value "%s" is not one of the allowed values for the field "%s". Allowed values: %s, but got: %s.';
    public const string EXPRESSION_EVAL = 'Error evaluating expression in strict mode.';
    public const string INVALID_VALUE_FIELD = 'The value "%s" is not valid for the field "%s". Expected %s, but got: %s.';

}
