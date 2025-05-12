<?php

namespace Tabula17\Satelles\Odf\Functions;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Picqer\Barcode\Renderers\JpgRenderer;
use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCodabar;
use Picqer\Barcode\Types\TypeCode11;
use Picqer\Barcode\Types\TypeCode128;
use Picqer\Barcode\Types\TypeCode128A;
use Picqer\Barcode\Types\TypeCode128B;
use Picqer\Barcode\Types\TypeCode128C;
use Picqer\Barcode\Types\TypeCode32;
use Picqer\Barcode\Types\TypeCode39;
use Picqer\Barcode\Types\TypeCode39Checksum;
use Picqer\Barcode\Types\TypeCode39Extended;
use Picqer\Barcode\Types\TypeCode39ExtendedChecksum;
use Picqer\Barcode\Types\TypeCode93;
use Picqer\Barcode\Types\TypeEan13;
use Picqer\Barcode\Types\TypeEan8;
use Picqer\Barcode\Types\TypeEanUpcBase;
use Picqer\Barcode\Types\TypeIntelligentMailBarcode;
use Picqer\Barcode\Types\TypeInterface;
use Picqer\Barcode\Types\TypeInterleaved25;
use Picqer\Barcode\Types\TypeInterleaved25Checksum;
use Picqer\Barcode\Types\TypeITF14;
use Picqer\Barcode\Types\TypeKix;
use Picqer\Barcode\Types\TypeMsi;
use Picqer\Barcode\Types\TypeMsiChecksum;
use Picqer\Barcode\Types\TypePharmacode;
use Picqer\Barcode\Types\TypePharmacodeTwoCode;
use Picqer\Barcode\Types\TypePlanet;
use Picqer\Barcode\Types\TypePostnet;
use Picqer\Barcode\Types\TypeRms4cc;
use Picqer\Barcode\Types\TypeStandard2of5;
use Picqer\Barcode\Types\TypeStandard2of5Checksum;
use Picqer\Barcode\Types\TypeTelepen;
use Picqer\Barcode\Types\TypeTelepenNumeric;
use Picqer\Barcode\Types\TypeUpcA;
use Picqer\Barcode\Types\TypeUpcE;
use Picqer\Barcode\Types\TypeUpcExtension2;
use Picqer\Barcode\Types\TypeUpcExtension5;

/**
 *
 */
class Advanced extends Base
{
    private array $mapBarcodes = [
        'codabar' => TypeCodabar::class,
        'code11' => TypeCode11::class,
        'code128' => TypeCode128::class,
        'code128a' => TypeCode128A::class,
        'code128b' => TypeCode128B::class,
        'code128c' => TypeCode128C::class,
        'code32' => TypeCode32::class,
        'code39' => TypeCode39::class,
        'code39checksum' => TypeCode39Checksum::class,
        'code39extended' => TypeCode39Extended::class,
        'code39extendedchecksum' => TypeCode39ExtendedChecksum::class,
        'code93' => TypeCode93::class,
        'ean13' => TypeEan13::class,
        'ean8' => TypeEan8::class,
        'eanupcbase' => TypeEanUpcBase::class,
        'itf14' => TypeITF14::class,
        'intelligentmailbarcode' => TypeIntelligentMailBarcode::class,
        'interface' => TypeInterface::class,
        'interleaved25' => TypeInterleaved25::class,
        'interleaved25checksum' => TypeInterleaved25Checksum::class,
        'kix' => TypeKix::class,
        'msi' => TypeMsi::class,
        'msichecksum' => TypeMsiChecksum::class,
        'pharmacode' => TypePharmacode::class,
        'pharmacodetwocode' => TypePharmacodeTwoCode::class,
        'planet' => TypePlanet::class,
        'postnet' => TypePostnet::class,
        'rms4cc' => TypeRms4cc::class,
        'standard2of5' => TypeStandard2of5::class,
        'standard2of5checksum' => TypeStandard2of5Checksum::class,
        'telepen' => TypeTelepen::class,
        'telepennumeric' => TypeTelepenNumeric::class,
        'upca' => TypeUpcA::class,
        'upce' => TypeUpcE::class,
        'upcextension2' => TypeUpcExtension2::class,
        'upcextension5' => TypeUpcExtension5::class,
    ];
    private array $mapBarcodeRenderers = [
        'png' => PngRenderer::class,
        'svg' => SvgRenderer::class,
        'jpg' => JpgRenderer::class,
        'jpeg' => JpgRenderer::class,
    ];
    private array $mapQRCodeRenderers = [
        'png' => PngWriter::class,
        'svg' => SvgWriter::class
    ];
    public string $workingDir {
        /**
         * @return string
         */
        get {
            return $this->workingDir;
        }
        /**
         * @return void
         */
        set {
            $this->workingDir = $value;
        }
    }

    /**
     * @param string|null $workingDir
     */
    public function __construct(?string $workingDir = null)
    {
        $this->workingDir = $workingDir ?? sys_get_temp_dir();
    }

    /**
     * Generates a barcode based on the provided parameters and saves it to a file.
     *
     * @param string $value The value to be encoded into the barcode.
     * @param string $type The type of barcode to generate (e.g., 'code128', 'qr').
     * @param float $width The width of the barcode.
     * @param float $height The height of the barcode.
     * @param string $outputFormat The format to render the barcode (e.g., 'png', 'svg').
     * @param string|null $fileName
     * @return string|null Returns the full file name of the saved barcode if successful, or null if an error occurs.
     */
    public function barcode(string $value, string $type, float $width, float $height, string $outputFormat, ?string $fileName = null): ?string
    {
        // $barcode = new Barcode($value);
        if (array_key_exists(strtolower($type), $this->mapBarcodes) && array_key_exists(strtolower($outputFormat), $this->mapBarcodeRenderers)) {
            $fullFileName = $this->workingDir . '/' . basename($fileName ?? uniqid('', true), strtolower($outputFormat)) . '.' . strtolower($outputFormat);

            $barcode = new $this->mapBarcodes[strtolower($type)]()->getBarcode($value);
            $renderer = new $this->mapBarcodeRenderers[strtolower($outputFormat)]();
            if (file_put_contents($fullFileName, $renderer->render($barcode, $width, $height))) {
                return $fullFileName;
            }
        }
        return null;
    }

    /**
     * Generates a QR code and saves it to a file.
     *
     * @param string $value The value or data to be encoded in the QR code.
     * @param int $size The size of the QR code in pixels.
     * @param string $outputFormat The file format for the generated QR code (e.g., png, jpg).
     * @param string|null $label Optional label text to include below the QR code.
     * @param string|null $logo Optional file path for a logo to overlay in the QR code.
     * @param string|null $fileName Optional name for the output file. If not provided, a unique name will be generated.
     * @return string|null The full file path of the generated QR code, or null if the output format is not supported.
     */
    public function qrcode(string $value, int $size, string $outputFormat, ?string $label = null, ?string $logo = null, ?string $fileName = null): ?string
    {
        if (array_key_exists(strtolower($outputFormat), $this->mapQRCodeRenderers)) {
            $fullFileName = $this->workingDir . '/' . basename($fileName ?? uniqid('', true), strtolower($outputFormat)) . '.' . strtolower($outputFormat);
            $args = [];
            $qrCode = new QrCode(
                data: $value,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: $size,
                margin: 10
            );
            $args[] = $qrCode;
            if ($logo) {
                $logo = new Logo(
                    path: $logo
                );
                $args[] = $logo;
            } else {
                $args[] = null;
            }
            if ($label) {

                $label = new Label(
                    text: $label,
                    textColor: new Color(255, 0, 0)
                );
                $args[] = $label;
            }

            $writer = new $this->mapQRCodeRenderers[strtolower($outputFormat)]();
            $file = $writer->write(...$args);
            $file->saveToFile($fullFileName);
            return $fullFileName;
        }
        return null;
    }
}