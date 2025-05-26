<?php

namespace Tabula17\Satelles\Odf\Exception;

class ExporterException extends RelatioException
{
    const string DEFAULT_MESSAGE = 'An error occurred while exporting the document to ODF: %s';
    const string SENDER_ERROR = 'Error sending the ODF document: %s';
    const string SENDER_NO_RETURN = 'Failed to send email: No message returned from transport.';
    public const string PRINTER_NOT_FOUND = 'Printer not found: %s';
}

