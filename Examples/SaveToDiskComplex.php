<?php
require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . DIRECTORY_SEPARATOR.'Data.php';

use Tabula17\Satelles\Odf\Converter\SofficeConverter;
use Tabula17\Satelles\Odf\DataRenderer;
use Tabula17\Satelles\Odf\Exporter\ExportToFile;
use Tabula17\Satelles\Odf\OdfContainer;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\OdfProcessor;

$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'tmp');
$zipHandler = new ZipArchive();
$fileContainer = new OdfContainer($zipHandler);
$functions = new Advanced($baseDir);
$data = random_data_complex();

$dataRenderer = new DataRenderer($data, $functions);

$template = __DIR__ . DIRECTORY_SEPARATOR . 'Templates/Report_Complex.odt';

$odfLoader = new OdfProcessor($template, $baseDir, $fileContainer, $dataRenderer);

$functions->workingDir = $odfLoader->workingDir;

$savesDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Saves');
$filename = "Complex Report generated - " . date('Y-m-d H:i:s');

$bin = '/Applications/LibreOffice.app/Contents/MacOS/soffice'; // <-- CLI path to LibreOffice (macOS)
$converter = new SofficeConverter('pdf', $odfLoader->workingDir, $bin);
$exporter = new ExportToFile($savesDir, $filename . ".pdf", $converter); // Convert to PDF
//$exporter = new ExportToFile($savesDir, $filename. ".odt"); // Without conversion

$odfLoader->loadFile()
    ->process($data)
    ->compile()
    ->exportTo($exporter)->cleanUpWorkingDir();