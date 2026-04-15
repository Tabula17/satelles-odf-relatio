<?php
// This file is part of the Satelles ODF library.
require __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../vendor/autoload.php');
include __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/media/data.php');

use Tabula17\Satelles\Odf\Converter\PandocConverter;
use Tabula17\Satelles\Odf\Converter\SofficeConverter;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\Exporter\ExportToFile;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;

$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'tmp');
$zipHandler = new ZipArchive();
$fileContainer = new OdfContainer($zipHandler);
$functions = new Advanced($baseDir);
$data = random_data_complex();

$dataRenderer = new DataRenderer($data, $functions);

$template = __DIR__ . DIRECTORY_SEPARATOR . 'templates/Report_Complex.odt';

$odfLoader = new OdfProcessor($template, $baseDir, $fileContainer, $dataRenderer);

$functions->workingDir = $odfLoader->workingDir;

$savesDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'saves');
$filename = "Complex Report generated - " . date('Y-m-d H:i:s');

$converter = null;
try {
    if (SofficeConverter::isInstalled()) {
        $converter = new SofficeConverter(format: 'pdf', outputDir: $odfLoader->workingDir, overwrite: false);
    } else if (PandocConverter::isInstalled()) {
        $converter = new PandocConverter(
            from: 'odt', outputDir: $odfLoader->workingDir, overwrite: false
        );
    } else {
        $filename = str_replace('.pdf', '.odt', $filename);
        trigger_error("No se encontró el binario de 'libreoffice' y tampoco el de 'pandoc', no podemos convertir el archivo", E_USER_NOTICE);
    }
} catch (ConversionException $e) {

}



$exporter = new ExportToFile($savesDir, $filename . ".pdf"); // Convert to PDF
//$exporter = new ExportToFile($savesDir, $filename. ".odt"); // Without conversion
$exporter->converter = $converter; // Set the converter to the exporter

$odfLoader->loadFile()
    ->process($data)
    ->compile()
    ->exportTo($exporter)->cleanUpWorkingDir();