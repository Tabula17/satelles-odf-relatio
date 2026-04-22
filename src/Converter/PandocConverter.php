<?php
declare(strict_types=1);

namespace Tabula17\Satelles\Odf\Converter;

use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\NonWritableFileException;
use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Swoole\Coroutine\System;
use Swoole\Coroutine;
use Throwable;

/**
 * Class PandocConverter
 *
 * Convierte archivos usando Pandoc (universal document converter).
 *
 * Características:
 * - Detección automática de Swoole para ejecución asíncrona
 * - Timeout configurable para evitar bloqueos
 * - Validación previa de existencia de Pandoc
 * - Soporte para múltiples formatos de entrada/salida
 * - Pool de procesos para mejor rendimiento
 *
 * @see https://pandoc.org/ para lista completa de formatos
 */
class PandocConverter implements ConverterInterface
{
    private const int DEFAULT_TIMEOUT = 30;
    private const int MAX_CONCURRENT_CONVERSIONS = 5;

    private static ?\Swoole\Coroutine\Channel $semaphore = null;
    private static ?string $cachedPandocPath = null;

    /**
     * Formatos de entrada comunes (para referencia)
     */
    public const INPUT_FORMATS = [
        'markdown', 'markdown_github', 'markdown_phpextra', 'commonmark',
        'html', 'html5',
        'latex', 'tex',
        'docx', 'odt',
        'rst', 'textile', 'mediawiki',
        'json', 'native',
    ];

    /**
     * Formatos de salida comunes (para referencia)
     */
    public const OUTPUT_FORMATS = [
        'pdf', 'html', 'html5',
        'docx', 'odt', 'pptx',
        'latex', 'beamer',
        'markdown', 'commonmark', 'gfm',
        'rst', 'textile', 'mediawiki',
        'epub', 'epub3',
        'plain', 'rtf',
    ];

    /**
     * @param string $from Formato de entrada (markdown, html, docx, etc.)
     * @param string $to Formato de salida (pdf, html, docx, etc.)
     * @param string|null $outputDir Directorio de salida
     * @param string|null $pandoc Ruta al binario (si es null, se detecta automáticamente)
     * @param bool $overwrite Sobrescribir archivos existentes
     * @param int $timeout Timeout en segundos para la conversión
     * @param array $extraOptions Opciones adicionales de pandoc (ej: ['--toc', '--standalone'])
     * @throws ConversionException
     */
    public function __construct(
        private readonly string $from = 'markdown',
        private readonly string $to = 'pdf',
        private ?string         $outputDir = null,
        private ?string         $pandoc = null,
        private readonly bool   $overwrite = true,
        private readonly int    $timeout = self::DEFAULT_TIMEOUT,
        private readonly array  $extraOptions = []
    )
    {
        $this->outputDir = $outputDir ?? sys_get_temp_dir();
        $this->pandoc = $pandoc ?? self::findPandocBinary();
        $this->initializeSemaphore();
    }

    /**
     * Inicializa el semáforo para control de concurrencia
     */
    private function initializeSemaphore(): void
    {
        if (self::$semaphore === null && $this->isSwooleAvailable()) {
            self::$semaphore = new \Swoole\Coroutine\Channel(self::MAX_CONCURRENT_CONVERSIONS);
        }
    }

    /**
     * Verifica si Swoole está disponible para ejecución asíncrona
     */
    private function isSwooleAvailable(): bool
    {
        return extension_loaded('swoole') &&
            class_exists('Swoole\Coroutine\System') &&
            Coroutine::getCid() > 0;
    }

    /**
     * Encuentra automáticamente la ruta del binario pandoc.
     *
     * @return string La ruta completa al ejecutable.
     * @throws ConversionException Si no se puede encontrar pandoc.
     */
    public static function findPandocBinary(): string
    {
        // 1. Devolver cache si ya se encontró
        if (self::$cachedPandocPath !== null) {
            return self::$cachedPandocPath;
        }

        $os = PHP_OS_FAMILY;
        $candidates = [];

        // Comando 'which' o 'where' según SO
        if ($os === 'Windows') {
            exec('where pandoc 2>NUL', $output, $returnVar);
            if ($returnVar === 0 && !empty($output[0])) {
                self::$cachedPandocPath = $output[0];
                return self::$cachedPandocPath;
            }

            // Rutas comunes en Windows (instalador .msi)
            $candidates = [
                'C:\Program Files\Pandoc\pandoc.exe',
                'C:\Program Files (x86)\Pandoc\pandoc.exe',
                getenv('LOCALAPPDATA') . '\Pandoc\pandoc.exe',
            ];
        } elseif ($os === 'Darwin') { // macOS
            exec('which pandoc 2>/dev/null', $output, $returnVar);
            if ($returnVar === 0 && !empty($output[0])) {
                self::$cachedPandocPath = $output[0];
                return self::$cachedPandocPath;
            }

            // Rutas comunes en macOS (Homebrew)
            $candidates = [
                '/opt/homebrew/bin/pandoc',  // Apple Silicon
                '/usr/local/bin/pandoc',      // Intel
                '/usr/bin/pandoc',
            ];
        } else { // Linux y otros Unix-like
            exec('which pandoc 2>/dev/null', $output, $returnVar);
            if ($returnVar === 0 && !empty($output[0])) {
                self::$cachedPandocPath = $output[0];
                return self::$cachedPandocPath;
            }

            // Rutas comunes en Linux
            $candidates = [
                '/usr/bin/pandoc',
                '/usr/local/bin/pandoc',
                '/opt/pandoc/bin/pandoc',
                '/snap/bin/pandoc',           // Instalación Snap
            ];
        }

        // 3. Verificar las rutas candidatas
        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                self::$cachedPandocPath = $candidate;
                return self::$cachedPandocPath;
            }
        }

        // 4. Si todo falla, lanzar una excepción clara
        throw new ConversionException(
            sprintf(ConversionException::DEFAULT_MESSAGE,
                'No se pudo encontrar automáticamente el ejecutable de Pandoc. ' .
                'Por favor, instala Pandoc (https://pandoc.org/installing.html) ' .
                'o especifica la ruta manualmente en el constructor.'
            )
        );
    }

    /**
     * Verifica que Pandoc esté disponible (cacheado)
     */
    private function isPandocAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        $command = $this->pandoc . ' --version 2>&1';
        $output = [];
        $returnVar = 0;

        exec($command, $output, $returnVar);
        $available = $returnVar === 0;

        if (!$available) {
            error_log("Pandoc no encontrado: " . implode("\n", $output));
        }

        return $available;
    }

    /**
     * Converts the given file to the specified format.
     *
     * @param string $file The file to be converted
     * @param string|null $outputName Optional desired name for the converted file
     * @return string|null The path to the converted file
     * @throws ConversionException
     */
    public function convert(ConverterJob $job): ConverterJob
    {
        $file = $job->file;
        $outputName = $job->output;
        // Validaciones previas
        $this->validateInput($file);

        // Verificar disponibilidad de Pandoc
        if (!$this->isPandocAvailable()) {
            throw new ConversionException(
                sprintf(ConversionException::DEFAULT_MESSAGE, 'Pandoc no está disponible en el sistema')
            );
        }

        // Obtener el nombre base del archivo de salida
        $extension = $this->to;
        $baseOutputName = $outputName ?? pathinfo($file, PATHINFO_FILENAME) . '.' . $extension;
        $generatedFile = $this->outputDir . DIRECTORY_SEPARATOR . $baseOutputName;
        // Verificar si ya existe y no sobrescribir
        if (!$this->overwrite && file_exists($generatedFile)) {
            $job->output = $generatedFile;
            $job->markCompleted();
            return $job;
        }

        // Ejecutar conversión (síncrona o asíncrona según entorno)
        $file = $this->isSwooleAvailable()
            ? $this->convertAsync($file, $generatedFile)
            : $this->convertSync($file, $generatedFile);
        if (file_exists($file)) {
            $job->output = $file;
            $job->markCompleted();
        } else {
            $job->output = null;
            $job->markFailed();
            $job->error = 'No se pudo generar el archivo';
        }
        return $job;
    }

    /**
     * Convierte contenido de texto directamente (sin archivo)
     *
     * @param string $content Contenido a convertir
     * @param string|null $inputFormat Formato de entrada (si es null, usa $this->from)
     * @return string Contenido convertido
     * @throws ConversionException
     */
    public function convertString(string $content, ?string $inputFormat = null): string
    {
        if (!$this->isPandocAvailable()) {
            throw new ConversionException('Pandoc no está disponible');
        }

        $from = $inputFormat ?? $this->from;

        // Crear archivo temporal
        $tempInput = tempnam($this->outputDir, 'pandoc_in_');
        file_put_contents($tempInput, $content);

        try {
            $command = sprintf(
                '%s -f %s -t %s %s %s',
                $this->pandoc,
                escapeshellarg($from),
                escapeshellarg($this->to),
                implode(' ', $this->extraOptions),
                escapeshellarg($tempInput)
            );

            if ($this->isSwooleAvailable()) {
                $result = System::exec($command);
                if ($result['code'] !== 0) {
                    throw new ConversionException($result['output']);
                }
                return $result['output'];
            }

            exec($command . ' 2>&1', $output, $returnVar);
            if ($returnVar !== 0) {
                throw new ConversionException(implode("\n", $output));
            }

            return implode("\n", $output);
        } finally {
            @unlink($tempInput);
        }
    }

    /**
     * Convierte de forma síncrona (CLI tradicional)
     * @throws ConversionException
     */
    private function convertSync(string $file, string $outputFile): string
    {
        $command = $this->buildCommand($file, $outputFile);
        $output = [];
        $returnVar = 0;

        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            throw new ConversionException(
                sprintf(
                    ConversionException::DEFAULT_MESSAGE,
                    sprintf(
                        RuntimeException::ACTION_ERROR_WITH_OUTPUT,
                        'ejecutando',
                        $command,
                        implode("\n", $output)
                    )
                )
            );
        }

        return $this->verifyAndReturnOutput($file, $outputFile);
    }

    /**
     * Convierte de forma asíncrona (Swoole)
     */
    private function convertAsync(string $file, string $outputFile): string
    {
        // Control de concurrencia
        if (self::$semaphore !== null) {
            self::$semaphore->push(true, $this->timeout);
        }

        try {
            $command = $this->buildCommand($file, $outputFile);

            // Ejecutar comando con timeout
            $result = System::exec($command);

            if ($result['code'] !== 0 || $result['signal'] !== 0) {
                throw new ConversionException(
                    sprintf(
                        ConversionException::DEFAULT_MESSAGE,
                        sprintf(
                            RuntimeException::ACTION_ERROR_WITH_OUTPUT,
                            'ejecutando',
                            $command,
                            $result['output']
                        )
                    )
                );
            }

            return $this->verifyAndReturnOutput($file, $outputFile);

        } finally {
            // Liberar semáforo
            if (self::$semaphore !== null) {
                self::$semaphore->pop();
            }
        }
    }

    /**
     * Versión asíncrona que devuelve el ID de la corrutina
     */
    public function convertAsyncWithCoroutine(ConverterJob $job, &$result): int|false
    {
        $file = $job->file;
        $outputName = $job->output;
        if (!$this->isSwooleAvailable()) {
            $result = $this->convert($job);
            return 0; // Indica ejecución síncrona
        }

        $extension = $this->to;
        $baseOutputName = $outputName ?? pathinfo($file, PATHINFO_FILENAME) . '.' . $extension;
        $generatedFile = $this->outputDir . DIRECTORY_SEPARATOR . $baseOutputName;

        $this->validateInput($file);

        if (!$this->overwrite && file_exists($generatedFile)) {
            $result = $generatedFile;
            return 0;
        }
        return Coroutine::create(function () use ($file, $generatedFile, $job, &$result) {
            try {
                $result = $this->convertAsync($file, $generatedFile);
                $job->output = $result;
                $job->markCompleted();
            } catch (Throwable $e) {
                $result = $e;
            }
        });
    }

    /**
     * Valida el archivo de entrada y directorio de salida
     */
    private function validateInput(string $file): void
    {
        if (!file_exists($file)) {
            throw new ConversionException(
                sprintf(ConversionException::DEFAULT_MESSAGE,
                    sprintf(FileNotFoundException::FILE_NOT_FOUND, $file)
                )
            );
        }

        if (!is_readable($file)) {
            throw new ConversionException(
                sprintf(ConversionException::DEFAULT_MESSAGE,
                    sprintf(FileException::CANT_READ, $file)
                )
            );
        }

        if (!is_dir($this->outputDir)) {
            if (!mkdir($concurrentDirectory = $this->outputDir, 0o755, true) && !is_dir($concurrentDirectory)) {
                throw new ConversionException(
                    sprintf(ConversionException::DEFAULT_MESSAGE,
                        sprintf(FileException::IS_NOT_DIRECTORY, $this->outputDir)
                    )
                );
            }
        }

        if (!is_writable($this->outputDir)) {
            throw new ConversionException(
                sprintf(ConversionException::DEFAULT_MESSAGE,
                    sprintf(NonWritableFileException::NON_WRITABLE_DIR, $this->outputDir)
                )
            );
        }
    }

    /**
     * Construye el comando de conversión
     */
    private function buildCommand(string $inputFile, string $outputFile): string
    {
        $escapedInput = escapeshellarg($inputFile);
        $escapedOutput = escapeshellarg($outputFile);

        $options = $this->extraOptions;

        // Añadir --standalone para formatos que lo necesitan
        if (in_array($this->to, ['html', 'html5', 'latex', 'beamer']) &&
            !in_array('--standalone', $options) &&
            !in_array('-s', $options)) {
            $options[] = '--standalone';
        }

        return sprintf(
            '%s -f %s -t %s %s -o %s %s',
            $this->pandoc,
            escapeshellarg($this->from),
            escapeshellarg($this->to),
            implode(' ', $options),
            $escapedOutput,
            $escapedInput
        );
    }

    /**
     * Verifica el archivo de salida y lo devuelve
     * @throws ConversionException
     */
    private function verifyAndReturnOutput(string $inputFile, string $expectedOutput): string
    {
        if (file_exists($expectedOutput) && filesize($expectedOutput) > 0) {
            return $expectedOutput;
        }

        throw new ConversionException(
            sprintf(ConversionException::FILE_RESULT_NOT_FOUND, $inputFile)
        );
    }

    /**
     * Método estático para verificar si Pandoc está instalado
     */
    public static function isInstalled(?string $pandocPath = null): bool
    {
        try {
            $path = $pandocPath ?? self::findPandocBinary();
        } catch (ConversionException) {
            return false;
        }

        $command = $path . ' --version 2>&1';
        exec($command, $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Obtiene la versión de Pandoc instalada
     */
    public static function getVersion(?string $pandocPath = null): ?string
    {
        try {
            $path = $pandocPath ?? self::findPandocBinary();
        } catch (ConversionException) {
            return null;
        }

        $command = $path . ' --version 2>&1';
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && !empty($output)) {
            // Pandoc muestra la versión en la primera línea: "pandoc 3.1.9"
            if (preg_match('/pandoc\s+([\d.]+)/i', $output[0], $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Lista los formatos de entrada soportados
     */
    public static function listInputFormats(?string $pandocPath = null): array
    {
        $path = $pandocPath ?? self::findPandocBinary();
        exec($path . ' --list-input-formats 2>&1', $output, $returnVar);

        return $returnVar === 0 ? $output : [];
    }

    /**
     * Lista los formatos de salida soportados
     */
    public static function listOutputFormats(?string $pandocPath = null): array
    {
        $path = $pandocPath ?? self::findPandocBinary();
        exec($path . ' --list-output-formats 2>&1', $output, $returnVar);

        return $returnVar === 0 ? $output : [];
    }

    /**
     * Limpieza de recursos estáticos
     */
    public static function cleanup(): void
    {
        if (self::$semaphore !== null) {
            self::$semaphore->close();
            self::$semaphore = null;
        }
    }
}