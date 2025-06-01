<?php
require __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../vendor/autoload.php');
include __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/media/data.php');

use Tabula17\Satelles\Odf\Converter\SofficeConverter;
use Tabula17\Satelles\Odf\Exporter\CupsIPPWrapper;
use Tabula17\Satelles\Odf\Exporter\ExportToFile;
use Tabula17\Satelles\Odf\Exporter\ExportToMail;
use Tabula17\Satelles\Odf\Exporter\ExportToPrinter;
use Tabula17\Satelles\Odf\Exporter\NetteMailWrapper;
use Tabula17\Satelles\Odf\Exporter\SymfonyMailerWrapper;
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


$cli_options = getopt('u:p:s:t:h:e:', ['transport:', 'printer:']);
$required_mail_options = ['u', 'p', 's', 't'];
$required_printer_options = ['h', 'e'];
$canSendMail = count(array_diff($required_mail_options, array_keys($cli_options))) === 0;
$canPrint = count(array_diff($required_printer_options, array_keys($cli_options))) === 0;
/**
 * EJMPLO DE USO:
 * symfony (GMAIL) => php examples/multipleActions.php -u YOUR_USERNAME -p 'YOUR APP PASS KEY' -s SENDER@gmail.com -t TO_ADDRESSES_BY_COMMA
 * NETTE => php examples/multipleActions.php -u YOUR_USERNAME -p YOUR_MAILPASS -s SENDER@EMAIL -t TO_ADDRESSES_BY_COMMA -h SMTP_HOST -e ENCRYPTION
 */


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

$filename = "Sales Order - " . $data['docNumber'] . ".pdf";


if (file_exists($soffice)) {
    $converter = new SofficeConverter(format: 'pdf', outputDir: $odfLoader->workingDir, soffice: $soffice, overwrite: false);
} else {
    $filename = str_replace('.pdf', '.odt', $filename);
    $converter = null;
    trigger_error("No se encontró el binario de libreoffice, no podemos convertir el archivo", E_USER_NOTICE);
}

if ($canSendMail) {
    $transport = 'symfony';
    if (array_key_exists('transport', $cli_options) && $cli_options['transport'] === 'nette') {
        $required_options[] = 'h';
        $required_options[] = 'e';
        $transport = 'nette';
    }
    $to = explode(',', $cli_options['t']);
    $mail = [
        'text' => 'Order ' . $data['docNumber'],
        'html' => '<h1>New order arrived!</h1> <p> Order N° ' . $data['docNumber'] . '</p>',
        'subject' => 'New order arrived!'
    ];
    if ($transport === 'symfony') {
        $mail_options = [
            'username' => $cli_options['u'],
            'appKey' => $cli_options['p'],
            'sender' => $cli_options['s']
        ];
        $dsn = 'gmail+smtp://' . $mail_options['username'] . ':' . rawurlencode($mail_options['appKey']) . '@default';
        $mailer = new SymfonyMailerWrapper($dsn, $mail_options['sender'], $to, $mail['subject'], $mail['text'], $mail['html']);
    } else {
        $mail_options = [
            'host' => $cli_options['s'],
            'username' => $cli_options['u'],
            'password' => $cli_options['p'],
            'sender' => $cli_options['s'],
            'encryption' => $cli_options['e']
        ];

        $mailer = new NetteMailWrapper((new Nette\Mail\SmtpMailer(
            host: $mail_options['host'],
            username: $mail_options['username'],
            password: $mail_options['password'], // your password
            encryption: $mail_options['encryption'], // or 'tls'
        )), $mail_options['sender'], $to, $mail['subject'], $mail['text'], $mail['html']);
    }

    $sender = new ExportToMail($mailer, $filename);
    $sender->converter = $converter;
}
$exporter = new ExportToFile($savesDir, $filename);
$exporter->converter = $converter;
if ($canPrint) {
    $cups = new CupsIPPWrapper('HP-Photosmart-C4380-series');
    $printer = new ExportToPrinter($cups);
}

$odfLoader->loadFile()
    ->process($data)
    ->compile();
$odfLoader->exportTo($exporter);
if ($canSendMail) {
    $odfLoader->exportTo($sender);
}
if ($canPrint) {
    $odfLoader->exportTo($printer);
}
$odfLoader->cleanUpWorkingDir();
