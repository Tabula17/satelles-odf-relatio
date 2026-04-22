<?php
declare(strict_types=1);

use Tabula17\Satelles\Odf\Converter\ConverterJob;
use Tabula17\Satelles\Odf\OdfProcessor;

include __DIR__ .'/../vendor/autoload.php';

$job = new ConverterJob(
    convertId: OdfProcessor::generateId('convert_'),
    exportId: OdfProcessor::generateId('export_'),
    jobId: OdfProcessor::generateId(),
    file: '../templates/Report_Complex.odt',
    output: '../saves/test.pdf',
);
var_dump($job);

function convert(ConverterJob $job): ConverterJob
{
    echo $job->convertId . PHP_EOL;
    echo $job->exportId . PHP_EOL;
    echo $job->jobId . PHP_EOL;
    echo $job->file . PHP_EOL;
    echo $job->output . PHP_EOL;

    $job->data = ['test' => 'test'];
    $job->markCompleted();

    return $job;
}

convert($job);

var_dump($job);