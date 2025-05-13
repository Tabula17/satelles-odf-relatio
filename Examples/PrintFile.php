<?php
require __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../vendor/autoload.php');
include __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/Media/Data.php');

use Tabula17\Satelles\Odf\Converter\SofficeConverter;
use Tabula17\Satelles\Odf\DataRenderer;
use Tabula17\Satelles\Odf\Exporter\CupsIPPWrapper;
use Tabula17\Satelles\Odf\Exporter\ExportToFile;
use Tabula17\Satelles\Odf\Exporter\ExportToMail;
use Tabula17\Satelles\Odf\Exporter\ExportToPrinter;
use Tabula17\Satelles\Odf\Exporter\NetteMailWrapper;
use Tabula17\Satelles\Odf\Exporter\SymfonyMailerWrapper;
use Tabula17\Satelles\Odf\OdfContainer;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\OdfProcessor;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

const SOFFICE_BIN = [
    'darwin' => '/Applications/LibreOffice.app/Contents/MacOS/soffice',
    'windows' => 'C:\Program Files\LibreOffice\program\soffice.exe',
    'linux' => '/usr/bin/soffice'
];
/**
 * Si la instalación se encuentra en otra ruta cambie los valores de la variable $soffice con la misma!
 */
$soffice = SOFFICE_BIN[strtolower(PHP_OS_FAMILY)] ?? SOFFICE_BIN['linux'];


$cli_options = getopt('', ['printer:']);
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
$template = __DIR__ . DIRECTORY_SEPARATOR . 'Templates/Report_Complex.odt';
$odfLoader = new OdfProcessor($template, $baseDir, $fileContainer, $dataRenderer);
$functions->workingDir = $odfLoader->workingDir;



$cups = new CupsIPPWrapper('HP-Photosmart-C4380-series');
$printer = new ExportToPrinter($cups);

$odfLoader->loadFile()
    ->process($data)
    ->compile();
$odfLoader->exportTo($printer);
$odfLoader->cleanUpWorkingDir();
