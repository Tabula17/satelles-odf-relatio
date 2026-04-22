<?php
declare(strict_types=1);

namespace Tabula17\Satelles\Odf\Converter;

use Override;
use Swoole\Coroutine\Channel;
use Tabula17\Satelles\Odf\ConverterInterface;
use Tabula17\Satelles\Odf\Exception\ConversionException;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\NonWritableFileException;
use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Swoole\Coroutine\System;
use Swoole\Coroutine;
use Tabula17\Satelles\Odf\Exporter\ExporterJob;
use Throwable;

/**
 * Class SofficeConverter
 *
 * Convierte archivos usando LibreOffice en modo headless.
 * Incluye detección automática de la ruta del ejecutable.
 */
class SofficeConverter implements ConverterInterface
{
    private const int DEFAULT_TIMEOUT = 30;
    private const int MAX_CONCURRENT_CONVERSIONS = 5;

    private static ?Channel $semaphore = null;
    private static ?string $cachedSofficePath = null;

    /**
     * @param string $format Formato de salida (pdf, docx, etc.)
     * @param string|null $outputDir Directorio de salida
     * @param string|null $soffice Ruta al binario (si es null, se detecta automáticamente)
     * @param bool $overwrite Sobrescribir archivos existentes
     * @param int $timeout Timeout en segundos para la conversión
     * @throws ConversionException
     */
    public function __construct(
        private readonly string $format = 'pdf',
        private ?string         $outputDir = null,
        private ?string         $soffice = null,
        private readonly bool   $overwrite = true,
        private readonly int    $timeout = self::DEFAULT_TIMEOUT
    )
    {
        $this->outputDir = $outputDir ?? sys_get_temp_dir();
        $this->soffice = $soffice ?? self::findSofficeBinary();
        $this->initializeSemaphore();
    }

    /**
     * Inicializa el semáforo para control de concurrencia
     */
    private function initializeSemaphore(): void
    {
        if (self::$semaphore === null && $this->isSwooleAvailable()) {
            self::$semaphore = new Channel(self::MAX_CONCURRENT_CONVERSIONS);
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
     * Encuentra automáticamente la ruta del binario soffice.
     *
     * @return string La ruta completa al ejecutable.
     * @throws ConversionException Si no se puede encontrar soffice.
     */
    public static function findSofficeBinary(): string
    {
        // 1. Devolver cache si ya se encontró
        if (self::$cachedSofficePath !== null) {
            return self::$cachedSofficePath;
        }

        // 2. Intentar detectar por sistema operativo
        $os = PHP_OS_FAMILY;
        $candidates = [];

        // Comando 'which' o 'where' según SO
        if ($os === 'Windows') {
            // En Windows, intentar con 'where'
            exec('where soffice 2>NUL', $output, $returnVar);
            if ($returnVar === 0 && !empty($output[0])) {
                self::$cachedSofficePath = $output[0];
                return self::$cachedSofficePath;
            }

            // Rutas comunes en Windows
            $candidates = [
                'C:\Program Files\LibreOffice\program\soffice.exe',
                'C:\Program Files (x86)\LibreOffice\program\soffice.exe',
                getenv('LOCALAPPDATA') . '\Programs\LibreOffice\program\soffice.exe',
            ];
        } elseif ($os === 'Darwin') { // macOS
            // En macOS, 'which' suele funcionar si está en PATH
            exec('which soffice 2>/dev/null', $output, $returnVar);
            if ($returnVar === 0 && !empty($output[0])) {
                self::$cachedSofficePath = $output[0];
                return self::$cachedSofficePath;
            }

            // Ruta estándar en macOS
            $candidates = [
                '/Applications/LibreOffice.app/Contents/MacOS/soffice',
                '/opt/homebrew/bin/soffice', // Apple Silicon
                '/usr/local/bin/soffice',     // Intel
            ];
        } else { // Linux y otros Unix-like
            exec('which soffice 2>/dev/null', $output, $returnVar);
            if ($returnVar === 0 && !empty($output[0])) {
                self::$cachedSofficePath = $output[0];
                return self::$cachedSofficePath;
            }

            // Rutas comunes en Linux
            $candidates = [
                '/usr/bin/soffice',
                '/usr/local/bin/soffice',
                '/opt/libreoffice/program/soffice',
                '/snap/bin/libreoffice',
                '/usr/lib/libreoffice/program/soffice',
            ];
        }

        // 3. Verificar las rutas candidatas
        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                self::$cachedSofficePath = $candidate;
                return self::$cachedSofficePath;
            }
        }

        // 4. Si todo falla, lanzar una excepción clara
        throw new ConversionException(
            sprintf(ConversionException::DEFAULT_MESSAGE,
                'No se pudo encontrar automáticamente el ejecutable de LibreOffice (soffice). ' .
                'Por favor, especifica la ruta manualmente en el constructor.'
            )
        );
    }

    /**
     * Verifica que LibreOffice esté disponible (cacheado)
     */
    private function isLibreOfficeAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        $command = $this->soffice . ' --version 2>&1';
        $output = [];
        $returnVar = 0;

        exec($command, $output, $returnVar);
        $available = $returnVar === 0;

        if (!$available) {
            error_log("LibreOffice no encontrado: " . implode("\n", $output));
        }

        return $available;
    }

    /**
     * Converts the given file to the specified format.
     *
     * @param ConverterJob $job
     * @return ConverterJob The path to the converted file
     * @throws ConversionException
     */
    #[Override]
    public function convert(ConverterJob $job): ConverterJob
    {
        $file = $job->file;
        $outputName = $job->output;
        // Validaciones previas
        $this->validateInput($file);

        // Verificar disponibilidad de LibreOffice
        if (!$this->isLibreOfficeAvailable()) {
            throw new ConversionException(
                sprintf(ConversionException::DEFAULT_MESSAGE, 'LibreOffice no está disponible en el sistema')
            );
        }

        // Obtener el nombre base del archivo de salida
        $baseOutputName = $outputName ?? pathinfo($file, PATHINFO_FILENAME) . '.' . $this->format;
        $generatedFile = $this->outputDir . DIRECTORY_SEPARATOR . $baseOutputName;

        // Verificar si ya existe y no sobrescribir
        if (!$this->overwrite && file_exists($generatedFile)) {
            $job->output = $generatedFile;
            $job->switchTo(ConverterOutputTypesEnum::Path);
            $job->markCompleted();
            return $job;
        }

        // Ejecutar conversión (síncrona o asíncrona según entorno)
        $file = $this->isSwooleAvailable()
            ? $this->convertAsync($file, $generatedFile)
            : $this->convertSync($file, $generatedFile);
        if (file_exists($file)) {
            $job->output = $file;
            $job->switchTo(ConverterOutputTypesEnum::Path);
            $job->markCompleted();
        } else {
            $job->output = null;
            $job->markFailed();
            $job->error = 'No se pudo generar el archivo';
        }
        return $job;
    }

    /**
     * Convierte de forma síncrona (CLI tradicional)
     * @throws ConversionException
     */
    private function convertSync(string $file, string $outputFile): string
    {
        $command = $this->buildCommand($file);

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
     * @throws ConversionException
     */
    private function convertAsync(string $file, string $outputFile): string
    {
        // Control de concurrencia
        self::$semaphore?->push(true, $this->timeout);

        try {
            $command = $this->buildCommand($file);

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
            self::$semaphore?->pop();
        }
    }

    /**
     * Versión asíncrona que devuelve el ID de la corrutina
     * para poder hacer wait después si se necesita
     * // Ejemplo de uso asíncrono con corrutinas
     * $converter = new SofficeConverter('pdf', '/tmp/output');
     * $result = null;
     *
     * $coroutineId = $converter->convertAsyncWithCoroutine(
     *      '/path/to/document.odt',
     *      'output.pdf',
     *      $result
     * );
     *
     * if ($coroutineId === 0) {
     *      // Ejecución síncrona (no Swoole)
     *      echo "Resultado síncrono: $result\n";
     * } elseif ($coroutineId === false) {
     *      // Error al crear corrutina
     *      echo "Error al crear corrutina\n";
     * } else {
     *      // Corrutina creada exitosamente
     *      echo "Conversión en proceso (corrutina ID: $coroutineId)\n";
     *
     *      // Hacer otras cosas mientras tanto...
     *
     *      // Si necesitas esperar el resultado:
     *      Coroutine::join([$coroutineId]);
     *
     *      if ($result instanceof Throwable) {
     *          echo "Error: " . $result->getMessage() . "\n";
     *      } else {
     *          echo "PDF generado: $result\n";
     *      }
     * }
     *
     * @return int|false ID de la corrutina o false si falla
     * @throws ConversionException
     */
    public function convertAsyncWithCoroutine(ConverterJob $job, &$result): int|false
    {
        $file = $job->file;
        $outputName = $job->output;
        if (!$this->isSwooleAvailable()) {
            $result = $this->convert($job);
            return 0; // Indica ejecución síncrona
        }

        $baseOutputName = $outputName ?? pathinfo($file, PATHINFO_FILENAME) . '.' . $this->format;
        $generatedFile = $this->outputDir . DIRECTORY_SEPARATOR . $baseOutputName;

        $this->validateInput($file);

        if (!$this->overwrite && file_exists($generatedFile)) {
            $result = $generatedFile;
            return 0;
        }
        // Crear corrutina y devolver su ID
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
     * // Uso:
     * $channel = $converter->convertWithChannel('/path/to/doc.odt', 'output.pdf');
     * $response = $channel->pop(); // Espera el resultado
     * if ($response['success']) {
     *      echo "PDF: " . $response['result'] . "\n";
     * }
     *
     * @param string $file
     * @param string|null $outputName
     * @return Channel
     */
    public function convertWithChannel(string $file, ?string $outputName = null): Channel
    {
        $channel = new Channel(1);
        $baseOutputName = $outputName ?? pathinfo($file, PATHINFO_FILENAME) . '.' . $this->format;
        $generatedFile = $this->outputDir . DIRECTORY_SEPARATOR . $baseOutputName;

        Coroutine::create(function () use ($file, $generatedFile, $channel) {
            try {
                $result = $this->convertAsync($file, $generatedFile);
                $channel->push(['success' => true, 'result' => $result]);
            } catch (Throwable $e) {
                $channel->push(['success' => false, 'error' => $e->getMessage()]);
            }
        });

        return $channel;
    }

    /**
     * Valida el archivo de entrada y directorio de salida
     * @throws ConversionException
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
    private function buildCommand(string $file): string
    {
        $escapedFile = escapeshellarg($file);
        $escapedOutput = escapeshellarg($this->outputDir);

        return sprintf(
            '%s --headless --convert-to %s %s --outdir %s',
            $this->soffice,
            escapeshellarg($this->format),
            $escapedFile,
            $escapedOutput
        );
    }

    /**
     * Verifica el archivo de salida y lo devuelve
     */
    private function verifyAndReturnOutput(string $inputFile, string $expectedOutput): string
    {
        // LibreOffice a veces genera nombres ligeramente diferentes
        $possibleFiles = [
            $expectedOutput,
            $this->outputDir . DIRECTORY_SEPARATOR . pathinfo($inputFile, PATHINFO_FILENAME) . '.' . $this->format,
            $this->outputDir . DIRECTORY_SEPARATOR . pathinfo($inputFile, PATHINFO_FILENAME) . '.' . strtoupper($this->format),
        ];

        foreach ($possibleFiles as $file) {
            if (file_exists($file) && filesize($file) > 0) {
                // Si el archivo esperado es diferente, renombrar
                if ($file !== $expectedOutput) {
                    rename($file, $expectedOutput);
                }
                return $expectedOutput;
            }
        }

        throw new ConversionException(
            sprintf(ConversionException::FILE_RESULT_NOT_FOUND, $inputFile)
        );
    }

    /**
     * Método estático para verificar si LibreOffice está instalado
     */
    public static function isInstalled(?string $sofficePath = null): bool
    {
        $path = $sofficePath ?? self::findSofficeBinary();
        $command = $path . ' --version 2>&1';
        exec($command, $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Obtiene la versión de LibreOffice instalada
     */
    public static function getVersion(?string $sofficePath = null): ?string
    {
        $path = $sofficePath ?? self::findSofficeBinary();
        $command = $path . ' --version 2>&1';
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && !empty($output)) {
            if (preg_match('/LibreOffice\s+([\d.]+)/i', implode(' ', $output), $matches)) {
                return $matches[1];
            }
        }

        return null;
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