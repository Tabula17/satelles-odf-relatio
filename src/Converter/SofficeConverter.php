<?php
declare(strict_types=1);

namespace Tabula17\Satelles\Odf\Converter;

use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\NonWritableFileException;
use Tabula17\Satelles\Odf\Exception\RuntimeException;

/**
 * Class SofficeConverter
 *
 * This class provides functionality to convert files into different formats using LibreOffice's command-line interface.
 * The conversion is managed by executing the `soffice` binary in headless mode.
 *
 * Implements the ConverterInterface for consistent interaction with other converters.
 */
class SofficeConverter implements ConverterInterface
{
    private string $sofficeBin;
    private string $format;
    private ?string $outputDir;
    /**
     * @var false|mixed
     */
    public mixed $overwrite;


    /**
     * @param string $format
     * @param string|null $outputDir
     * @param string $soffice
     * @param $overwrite
     */
    /**
     * @param string $format
     * @param string|null $outputDir
     * @param string $soffice
     * @param bool $overwrite
     */
    public function __construct(string $format = 'pdf', ?string $outputDir = null, string $soffice = 'soffice', bool $overwrite = true)
    {
        $this->format = $format;
        $this->outputDir = $outputDir ?? __DIR__;
        $this->sofficeBin = $soffice;
        $this->overwrite = $overwrite;
    }

    /**
     * Converts the given file to the specified format and saves the output to the output directory.
     * If an output name is provided, the generated file will be renamed.
     *
     * @param string $file The file to be converted. Must be an existing and readable file.
     * @param string|null $outputName Optional. The desired name for the converted file.
     * @return string|null The path to the converted file, or null if the conversion fails.
     * @throws ConversionException
     */
    public function convert(string $file, ?string $outputName = null): ?string
    {
        if (!file_exists($file)) {
            throw new ConversionException(sprintf(ConversionException::DEFAULT_MESSAGE, sprintf(FileNotFoundException::FILE_NOT_FOUND, $file)));
        }
        if (!is_dir($this->outputDir)) {
            throw new ConversionException(sprintf(ConversionException::DEFAULT_MESSAGE, sprintf(FileException::IS_NOT_DIRECTORY, $this->outputDir)));
        }
        if (!is_writable($this->outputDir)) {
            throw new ConversionException(sprintf(ConversionException::DEFAULT_MESSAGE, sprintf(NonWritableFileException::NON_WRITABLE_DIR, $this->outputDir)));
        }
        $generated_file = $this->outputDir . DIRECTORY_SEPARATOR . explode('.', basename($file))[0] . '.' . $this->format;
        $escapedFile = (str_replace(' ', '\\ ', $file));
        $escapedOutput = (str_replace(' ', '\\ ', $this->outputDir));

        $command = "$this->sofficeBin --headless --convert-to pdf $escapedFile --outdir  $escapedOutput";
        if ($this->overwrite || !file_exists($generated_file)) {
            exec($command, $output, $returnVar);
            if ($returnVar !== 0) {
                throw new ConversionException(sprintf(ConversionException::DEFAULT_MESSAGE, sprintf(RuntimeException::ACTION_ERROR_WITH_OUTPUT, 'ejecutando', $command, implode("\n", $output))));
            }
        }
        $out = $generated_file;
        if ($outputName && rename($generated_file, $this->outputDir . DIRECTORY_SEPARATOR . $outputName)) {
            $out = $this->outputDir . DIRECTORY_SEPARATOR . $outputName;
        }
        if (!file_exists($out)) {
            throw new ConversionException(sprintf(ConversionException::FILE_RESULT_NOT_FOUND, $file));
        }
        return $out;
    }

}