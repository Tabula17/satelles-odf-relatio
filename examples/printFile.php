<?php
require __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../vendor/autoload.php');
include __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/media/data.php');

use Tabula17\Satelles\Odf\Exporter\ExportToPrinter;
use Tabula17\Satelles\Odf\Exporter\SatellesCupsIPPWrapper;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;

const SOFFICE_BIN = [
    'darwin' => '/Applications/LibreOffice.app/Contents/MacOS/soffice',
    'windows' => 'C:\Program Files\LibreOffice\program\soffice.exe',
    'linux' => '/usr/bin/soffice'
];
/**
 * Si la instalación se encuentra en otra ruta cambie los valores de la variable $soffice con la misma!
 */
$soffice = SOFFICE_BIN[strtolower(PHP_OS_FAMILY)] ?? SOFFICE_BIN['linux'];


$cli_options = getopt('', ['printer:', 'host:', 'port:']);
$required_options = ['printer'];
if (!$cli_options) {
    throw new RuntimeException("Missing options");
}
$options_missing = array_diff($required_options, array_keys($cli_options));
if (count($options_missing) > 0) {
    throw new RuntimeException("Missing options: " . implode(', ', $options_missing));
}
$to = explode(',', $cli_options['t']);
$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'tmp');
$zipHandler = new ZipArchive();
$fileContainer = new OdfContainer($zipHandler);
$functions = new Advanced($baseDir);
$data = random_data_complex();

$dataRenderer = new DataRenderer($data, $functions);
$template = __DIR__ . DIRECTORY_SEPARATOR . 'templates/Report_Complex.odt';
$odfLoader = new OdfProcessor($template, $baseDir, $fileContainer, $dataRenderer);
$functions->workingDir = $odfLoader->workingDir;


$cups = new SatellesCupsIPPWrapper($cli_options['host'] ?? 'localhost', $cli_options['port'] ?? 631, $cli_options['printer'] ?? 'default');
$printer = new ExportToPrinter($cups);

$odfLoader->loadFile()
    ->process($data)
    ->compile();
$odfLoader->exportTo($printer);
$odfLoader->cleanUpWorkingDir();
