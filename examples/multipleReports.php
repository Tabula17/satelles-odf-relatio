<?php

declare(strict_types=1);

require __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../vendor/autoload.php');
include __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/media/data.php');

use Tabula17\Satelles\Odf\Exporter\ExportToFile;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\Template\OdfTemplate;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;

/**
 * Genera un reporte por cada set de datos reutilizando la estructura del
 * template ya parseada. Solo se descomprime y parsea el ODT una vez.
 *
 * Uso:
 *   php examples/multipleReports.php
 */

$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'tmp');
$savesDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'saves');
$template = __DIR__ . DIRECTORY_SEPARATOR . 'templates/Report_Complex.odt';

$functions = new Advanced($baseDir);
$renderer = new DataRenderer([], $functions);

// El template se cachea here; createProcessor() clona las partes por cada job.
$odfTemplate = new OdfTemplate(
    $template,
    $baseDir,
    new OdfContainer(new ZipArchive()),
    $renderer
);
$odfTemplate->load(); // warm-up opcional (útil en workers Swoole de larga duración)

$dataSets = [
    random_data_complex(),
    random_data_complex(),
    random_data_complex(),
];

$results = $odfTemplate->processAll(
    $dataSets,
    static fn(int $index, $processor): string =>
        $savesDir . DIRECTORY_SEPARATOR . 'report-' . ($index + 1) . '.odt'
);

foreach ($results as $index => $result) {
    echo sprintf(
        "Reporte %d: %s (%d exporter, %.2f ms)%s",
        $index + 1,
        $result['processId'],
        count($result['exporters']),
        $result['duration'],
        PHP_EOL
    );
}

// Alternativa: control total por job (por ejemplo, exportar a PDF y mail).
foreach ($dataSets as $index => $data) {
    $job = $odfTemplate->createProcessor();
    try {
        $job->process($data)
            ->compile()
            ->exportTo(new ExportToFile($savesDir, 'manual-' . ($index + 1) . '.odt'));
    } finally {
        $job->cleanUpWorkingDir();
    }
}
