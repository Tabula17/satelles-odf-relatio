<?php
require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . DIRECTORY_SEPARATOR.'Data.php';

use Tabula17\Satelles\Odf\Converter\SofficeConverter;
use Tabula17\Satelles\Odf\DataRenderer;
use Tabula17\Satelles\Odf\Exporter\ExportToMail;
use Tabula17\Satelles\Odf\Exporter\NetteMailWrapper;
use Tabula17\Satelles\Odf\Exporter\SymfonyMailerWrapper;
use Tabula17\Satelles\Odf\OdfContainer;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\OdfProcessor;

$cli_options = getopt('u:p:s:t:h:e:', ['transport:']);
$required_options = ['u', 'p', 's', 't'];
if(!$cli_options){
    throw new Exception("Missing options");
}

/**
 * Example!
 * symfony (GMAIL) => php Examples/SendMail.php -u YOUR_USERNAME -p YOUR_APPKEY -s SENDER@EMAIL -t TO_ADDRESSES_BY_COMMA
 * NETTE => php Examples/SendMail.php -u YOUR_USERNAME -p YOUR_MAILPASS -s SENDER@EMAIL -t TO_ADDRESSES_BY_COMMA -h SMTP_HOST -e ENCRYPTION
 */


$transport = 'symfony';

if (array_key_exists('transport', $cli_options) && $cli_options['transport'] === 'nette') {
    $required_options[] = 'h';
    $required_options[] = 'e';
    $transport = 'nette';
}
$options_missing = array_diff($required_options, array_keys($cli_options));
if (count($options_missing) > 0) {
    throw new Exception("Missing options: " . implode(', ', $options_missing));
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
$savesDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Saves');

$filename = "Sales Order - " . $data['docNumber'] . ".pdf";

$bin = '/Applications/LibreOffice.app/Contents/MacOS/soffice'; // <-- CLI path to LibreOffice (macOS)
$converter = new SofficeConverter('pdf', $odfLoader->workingDir, $bin);

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

    $mailer = new SymfonyMailerWrapper($dsn, $mail_options['sender'], $to,
        $mail['subject'], $mail['text'], $mail['html']);
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


$exporter = new ExportToMail($mailer, $filename, $converter); // Send with Symfony

$odfLoader->loadFile()
    ->process($data)
    ->compile()
    ->exportTo($exporter)
    ->cleanUpWorkingDir();
