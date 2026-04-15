<?php

declare(strict_types=1);

namespace Tabula17\Satelles\Odf\Functions;

use Override;
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
use Throwable;

/**
 * Advanced class for generating barcodes and QR codes in ODF templates.
 */
class Advanced extends Base
{

    /**
     * The mapping of barcode generators.
     */
    private const array BARCODE_TYPES = [
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

    /**
     * The mapping of output formats to barcode renderers.
     */
    private const array BARCODE_RENDERERS = [
        'png' => PngRenderer::class,
        'svg' => SvgRenderer::class,
        'jpg' => JpgRenderer::class,
        'jpeg' => JpgRenderer::class,
    ];

    /**
     * The mapping of output formats to QR code renderers.
     */
    private const array QRCODE_RENDERERS = [
        'png' => PngWriter::class,
        'svg' => SvgWriter::class
    ];

    /**
     * @param string|null $workingDir Working directory for generated files
     */
    public function __construct(?string $workingDir = null)
    {
        //$this->workingDir = $workingDir ?? sys_get_temp_dir();
        parent::__construct($workingDir ?? sys_get_temp_dir());
    }

    /**
     * Generates a barcode based on the provided parameters and saves it to a file.
     *
     * @param string $value The value to be encoded into the barcode
     * @param string $type The type of barcode to generate (e.g., 'code128', 'qr')
     * @param float $width The width of the barcode in pixels
     * @param float $height The height of the barcode in pixels
     * @param string $outputFormat The format to render the barcode (e.g., 'png', 'svg', 'jpg')
     * @param string|null $fileName Optional custom file name (without extension)
     * @return string|null Returns the full file path of the saved barcode if successful, or null if an error occurs
     */
    public function barcode(
        string $value,
        string $type,
        float $width,
        float $height,
        string $outputFormat,
        ?string $fileName = null
    ): ?string {
        $typeLower = strtolower($type);
        $formatLower = strtolower($outputFormat);

        // Validar que el tipo de código de barras y el formato sean soportados
        if (!isset(self::BARCODE_TYPES[$typeLower])) {
            throw new \InvalidArgumentException("Unsupported barcode type: {$type}");
        }

        if (!isset(self::BARCODE_RENDERERS[$formatLower])) {
            throw new \InvalidArgumentException("Unsupported output format: {$outputFormat}");
        }

        try {
            // Generar nombre de archivo único si no se proporciona
            $baseFileName = $fileName ?? uniqid('barcode_', true);
            $fullFileName = $this->workingDir . '/' . $baseFileName . '.' . $formatLower;

            // Asegurar que el directorio existe
            $this->ensureDirectoryExists(dirname($fullFileName));

            // Generar el código de barras
            $barcodeType = self::BARCODE_TYPES[$typeLower];
            $barcode = new $barcodeType()->getBarcode($value);

            // Renderizar y guardar
            $rendererClass = self::BARCODE_RENDERERS[$formatLower];
            $renderer = new $rendererClass();
            $rendered = $renderer->render($barcode, $width, $height);

            if (file_put_contents($fullFileName, $rendered) !== false) {
                return $fullFileName;
            }

            return null;
        } catch (Throwable $e) {
            // Log del error si es necesario
            error_log("Barcode generation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generates a QR code and saves it to a file.
     *
     * @param string $value The value or data to be encoded in the QR code
     * @param int $size The size of the QR code in pixels
     * @param string $outputFormat The file format for the generated QR code (png, svg)
     * @param string|null $label Optional label text to include below the QR code
     * @param string|null $logo Optional file path for a logo to overlay in the QR code
     * @param string|null $fileName Optional custom file name (without extension)
     * @return string|null The full file path of the generated QR code, or null if the output format is not supported
     */
    public function qrcode(
        string $value,
        int $size,
        string $outputFormat,
        ?string $label = null,
        ?string $logo = null,
        ?string $fileName = null
    ): ?string {
        $formatLower = strtolower($outputFormat);

        // Validar que el formato sea soportado
        if (!isset(self::QRCODE_RENDERERS[$formatLower])) {
            throw new \InvalidArgumentException("Unsupported QR code output format: {$outputFormat}");
        }

        try {
            // Generar nombre de archivo único si no se proporciona
            $baseFileName = $fileName ?? uniqid('qrcode_', true);
            $fullFileName = $this->workingDir . '/' . $baseFileName . '.' . $formatLower;

            // Asegurar que el directorio existe
            $this->ensureDirectoryExists(dirname($fullFileName));

            // Configurar el QR code
            $qrCode = new QrCode(
                data: $value,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: $size,
                margin: 10
            );

            // Preparar argumentos para el writer
            $writerArgs = [$qrCode];

            // Añadir logo si se proporciona
            if ($logo && file_exists($logo)) {
                $writerArgs[] = new Logo(path: $logo);
            } else {
                $writerArgs[] = null;
            }

            // Añadir label si se proporciona
            if ($label) {
                $writerArgs[] = new Label(
                    text: $label,
                    textColor: new Color(255, 0, 0)
                );
            }

            // Generar y guardar
            $writerClass = self::QRCODE_RENDERERS[$formatLower];
            $writer = new $writerClass();
            $result = $writer->write(...$writerArgs);
            $result->saveToFile($fullFileName);

            return $fullFileName;
        } catch (Throwable $e) {
            // Log del error si es necesario
            error_log("QR code generation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Ensures that a directory exists, creating it if necessary
     *
     * @param string $directory Directory path
     * @return void
     * @throws \RuntimeException If directory cannot be created
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create directory: {$directory}");
        }
    }

    /**
     * Gets the current working directory
     *
     * @return string|null
     * @deprecated Use the $workingDir property directly
     */
    public function getWorkingDir(): ?string
    {
        return $this->workingDir;
    }

    /**
     * Sets the working directory
     *
     * @param string|null $workingDir
     * @return self
     * @deprecated Use the $workingDir property directly
     */
    public function setWorkingDir(?string $workingDir): self
    {
        $this->workingDir = $workingDir;
        return $this;
    }
}