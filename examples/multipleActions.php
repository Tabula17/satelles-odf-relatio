<?php
require __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../vendor/autoload.php');
include __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/media/data.php');

use Tabula17\Satelles\Odf\Converter\PandocConverter;
use Tabula17\Satelles\Odf\Converter\SofficeConverter;
use Tabula17\Satelles\Odf\Exception\CompilationException;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\ValidationException;
use Tabula17\Satelles\Odf\Exporter\ExportToFile;
use Tabula17\Satelles\Odf\Exporter\ExportToMail;
use Tabula17\Satelles\Odf\Exporter\ExportToPrinter;
use Tabula17\Satelles\Odf\Exporter\NetteMailWrapper;
use Tabula17\Satelles\Odf\Exporter\SatellesCupsIPPWrapper;
use Tabula17\Satelles\Odf\Exporter\SymfonyMailerWrapper;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;


$cli_options = getopt('u:p:s:t:h:e:', ['transport:', 'printer:', 'host:', 'port:']);
$required_mail_options = ['u', 'p', 's', 't'];
$required_printer_options = ['h', 'e'];
$canSendMail = $cli_options
        |> array_keys(...)
        |> (static fn($x) => array_diff($required_mail_options, $x))
        |> count(...) === 0;
$canPrint = $cli_options
        |> array_keys(...)
        |> (static fn($x) => array_diff($required_printer_options, $x))
        |> count(...) === 0;
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
try {
    $odfLoader = new OdfProcessor($template, $baseDir, $fileContainer, $dataRenderer);
} catch (FileNotFoundException|FileException $e) {
    exit($e->getMessage());
}
$functions->workingDir = $odfLoader->workingDir;
$savesDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'saves');

$filename = "Sales Order - " . $data['docNumber'] . ".pdf";

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
    $cups = new SatellesCupsIPPWrapper($cli_options['host'] ?? 'localhost', $cli_options['port'] ?? 631, $cli_options['printer'] ?? 'default');
    $printer = new ExportToPrinter($cups);
}

try {
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
} catch (CompilationException|\Tabula17\Satelles\Odf\Exception\RuntimeException|ValidationException $e) {

} finally {

    $odfLoader->cleanUpWorkingDir();
}
