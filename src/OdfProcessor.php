<?php

declare(strict_types=1);

namespace Tabula17\Satelles\Odf;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Tabula17\Satelles\Odf\Exception\CompilationException;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\NonWritableFileException;
use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;
use Tabula17\Satelles\Odf\Exception\ValidationException;
use Tabula17\Satelles\Odf\Exporter\ExporterJobCollection;
use Tabula17\Satelles\Odf\Exporter\ExporterJob;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;
use Tabula17\Satelles\Odf\Template\XmlProcessor;
use Throwable;
use ZipArchive;

/**
 * Class OdfProcessor
 *
 * Optimizado para entornos de alto rendimiento (Swoole).
 */
class OdfProcessor
{
    private const string TEMP_PREFIX = 'j17';
    private const int DIRECTORY_PERMISSIONS = 0o755;

    // Pool estático de ZipArchive para reutilización
    private static array $zipArchivePool = [];
    private static int $maxPoolSize = 10;

    // Flag para habilitar procesamiento paralelo (solo en Swoole Server)
    private static bool $parallelProcessingEnabled = false;

    private bool $fileIsLoaded = false;
    private bool $fileIsCompiled = false;
    private bool $shouldCleanup = true;
    private DataRenderer $renderer;
    private XmlProcessor $xmlProcessor;
    public ExporterJobCollection $exporterResults;
    public array $exporterErrors = [];

    private(set) string $workingDir;
    private ?string $processId;
    private ?float $startedAt;
    private ?float $finishedAt;

    /**
     * Habilita o deshabilita el procesamiento paralelo con corrutinas
     * Solo debe habilitarse dentro de un Swoole Server, no en scripts CLI
     */
    public static function enableParallelProcessing(bool $enable = true): void
    {
        self::$parallelProcessingEnabled = $enable &&
            extension_loaded('swoole') &&
            class_exists(Coroutine::class) &&
            Coroutine::getCid() > 0; // Solo si ya estamos en una corrutina
    }

    /**
     * @param string $filePath Path to the ODF file
     * @param string $baseDir Base directory for temporary files
     * @param OdfContainer|null $fileContainer Pre-configured container
     * @param DataRenderer|null $renderer Data renderer
     * @param XmlProcessorInterface|null $xmlProcessor Custom XML processor
     * @throws FileNotFoundException
     * @throws FileException
     */
    public function __construct(
        private readonly string        $filePath,
        string                         $baseDir,
        private readonly ?OdfContainer $fileContainer = null,
        ?DataRenderer                  $renderer = null,
        ?XmlProcessorInterface         $xmlProcessor = null
    )
    {
        if (!file_exists($this->filePath)) {
            throw new FileNotFoundException(sprintf(FileNotFoundException::FILE_NOT_FOUND, $this->filePath));
        }

        if (!is_readable($this->filePath)) {
            throw new FileException(sprintf(FileException::CANT_READ, $this->filePath));
        }

        $this->setWorkingDirectory($baseDir);
        $this->exporterResults = new ExporterJobCollection();
        $container = $fileContainer ?? new OdfContainer($this->getZipArchive());
        $this->renderer = $renderer ?? new DataRenderer([], null);
        $this->xmlProcessor = $xmlProcessor ?? new XmlProcessor($this->renderer, $container);
    }

    /**
     * Destructor - Limpieza automática
     */
    public function __destruct()
    {
        if ($this->shouldCleanup && isset($this->workingDir)) {
            $this->silentCleanup();
        }
    }

    /**
     * Obtiene un ZipArchive del pool
     */
    private function getZipArchive(): ZipArchive
    {
        // Solo usar pool en entornos Swoole Server (no en CLI)
        if (self::$parallelProcessingEnabled && !empty(self::$zipArchivePool)) {
            return array_pop(self::$zipArchivePool);
        }

        return new ZipArchive();
    }

    /**
     * Devuelve un ZipArchive al pool
     */
    private function returnZipArchive(ZipArchive $zip): void
    {
        if (self::$parallelProcessingEnabled && count(self::$zipArchivePool) < self::$maxPoolSize) {
            self::$zipArchivePool[] = $zip;
        }
    }

    /**
     * Desactiva la limpieza automática
     */
    public function disableAutoCleanup(): self
    {
        $this->shouldCleanup = false;
        return $this;
    }

    /**
     * Reinicia el estado del procesador
     */
    public function reset(): self
    {
        $this->fileIsLoaded = false;
        $this->fileIsCompiled = false;
        $this->exporterResults->clear();
        $this->exporterErrors = [];
        $this->renderer->data = null;
        $this->processId = null;
        $this->startedAt = null;
        $this->finishedAt = null;


        return $this;
    }

    private function generateId(string $prefix = 'tplJob_'): string
    {
        return $prefix . bin2hex(random_bytes(8));
    }

    /**
     * Processes the ODF file with the provided data.
     */
    public function process(array $data, ?string $alias = null): self
    {
        if ($data === []) {
            throw new ValidationException(sprintf(ValidationException::EMPTY_FIELD, 'La matriz data'));
        }

        if ($alias !== null) {
            $this->validateAlias($alias);
        }

        if (!$this->fileIsLoaded) {
            $this->loadFile();
        }

        $this->renderer->data = $data;
        $this->fileIsCompiled = false;

        $this->processXmlFiles($data, $alias);

        return $this;
    }

    /**
     * Valida el formato del alias
     */
    private function validateAlias(string $alias): void
    {
        static $aliasCache = [];

        if (isset($aliasCache[$alias])) {
            if (!$aliasCache[$alias]) {
                throw new ValidationException(sprintf(ValidationException::CONTAINS_INVALID_CHARS, $alias));
            }
            return;
        }

        if ($alias === '') {
            $aliasCache[$alias] = false;
            throw new ValidationException(sprintf(ValidationException::EMPTY_STRING, 'Alias'));
        }

        $valid = preg_match('/^[a-zA-Z0-9_]+$/', $alias) === 1;
        $aliasCache[$alias] = $valid;

        if (!$valid) {
            throw new ValidationException(sprintf(ValidationException::CONTAINS_INVALID_CHARS, $alias));
        }
    }

    /**
     * Procesa archivos XML - versión segura sin warnings
     */
    private function processXmlFiles(array $data, ?string $alias): void
    {
        $content = $this->fileContainer->getPart(XmlMemberPath::CONTENT);
        $styles = $this->fileContainer->getPart(XmlMemberPath::STYLES);

        // Solo usar paralelismo si está explícitamente habilitado Y estamos en Swoole Server
        if (self::$parallelProcessingEnabled && Coroutine::getCid() > 0) {
            $this->processXmlFilesParallel($content, $styles, $data, $alias);
        } else {
            // Procesamiento síncrono (sin warnings)
            $this->xmlProcessor->processTemplate($content, $data, $alias);
            $this->xmlProcessor->processTemplate($styles, $data, $alias);
        }
    }

    /**
     * Procesamiento paralelo seguro (solo dentro de Swoole Server)
     */
    private function processXmlFilesParallel($content, $styles, array $data, ?string $alias): void
    {
        $channel = new Channel(2);

        // Procesar content.xml en corrutina
        Coroutine::create(function () use ($content, $data, $alias, $channel) {
            try {
                $this->xmlProcessor->processTemplate($content, $data, $alias);
                $channel->push(['success' => true]);
            } catch (Throwable $e) {
                $channel->push(['success' => false, 'error' => $e]);
            }
        });

        // Procesar styles.xml en corrutina
        Coroutine::create(function () use ($styles, $data, $alias, $channel) {
            try {
                $this->xmlProcessor->processTemplate($styles, $data, $alias);
                $channel->push(['success' => true]);
            } catch (Throwable $e) {
                $channel->push(['success' => false, 'error' => $e]);
            }
        });

        // Esperar resultados
        for ($i = 0; $i < 2; $i++) {
            $result = $channel->pop();
            if (!$result['success']) {
                throw $result['error'];
            }
        }

        $channel->close();
    }

    /**
     * Sets the working directory for temporary files.
     */
    private function setWorkingDirectory(string $baseDir): void
    {
        if (!$this->isValidDirectory($baseDir)) {
            throw new NonWritableFileException(sprintf(FileException::CANT_OVERWRITE, $baseDir));
        }
        $this->workingDir = $this->createUniqueWorkingDirectory($baseDir);
    }

    /**
     * Checks if the provided directory is valid.
     */
    private function isValidDirectory(string $dir): bool
    {
        static $validatedDirs = [];

        if (isset($validatedDirs[$dir])) {
            return $validatedDirs[$dir];
        }

        $realPath = realpath($dir);
        $valid = $realPath !== false && is_dir($realPath) && is_writable($realPath);
        $validatedDirs[$dir] = $valid;

        return $valid;
    }

    /**
     * Creates a unique working directory.
     */
    private function createUniqueWorkingDirectory(string $baseDir): string
    {
        $maxAttempts = 10;
        $attempt = 0;
        $backoff = 1000;

        while ($attempt < $maxAttempts) {
            $suffix = \bin2hex(\random_bytes(8));
            $workingDir = $baseDir . DIRECTORY_SEPARATOR . self::TEMP_PREFIX . '_' . $suffix;

            if (!file_exists($workingDir)) {
                if (mkdir($workingDir, self::DIRECTORY_PERMISSIONS, true) || is_dir($workingDir)) {
                    return $workingDir;
                }
            }

            $attempt++;
            if ($attempt < $maxAttempts) {
                usleep($backoff * $attempt);
            }
        }

        throw new NonWritableFileException(
            sprintf(FileException::CANT_CREATE, $workingDir ?? 'directorio temporal')
        );
    }

    /**
     * Loads the ODF file into memory.
     */
    public function loadFile(): self
    {
        $this->validateStateForLoading();
        $this->processId = $this->generateId();
        $this->startedAt = microtime(true);
        $odfFile = $this->workingDir . DIRECTORY_SEPARATOR . basename($this->filePath);

        try {
            if (!@copy($this->filePath, $odfFile)) {
                $error = error_get_last();
                throw new FileException(
                    sprintf(FileException::CANT_COPY, $this->filePath, $odfFile) .
                    ($error ? ': ' . $error['message'] : '')
                );
            }

            $this->fileIsCompiled = false;

            try {
                $this->fileContainer->loadFile($odfFile);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    sprintf(RuntimeException::FAILED_TO_LOAD, $odfFile) . ': ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            $this->fileIsLoaded = true;
            return $this;

        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf(RuntimeException::ACTION_ERROR, 'opening', $odfFile) . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Valida el estado interno antes de cargar el archivo
     */
    private function validateStateForLoading(): void
    {
        if ($this->filePath === '') {
            throw new ValidationException(sprintf(ValidationException::VALUE_NOT_SET, "filePath"));
        }

        if (!isset($this->workingDir) || $this->workingDir === '') {
            throw new ValidationException(sprintf(ValidationException::VALUE_NOT_SET, "workingDir"));
        }

        if (!file_exists($this->filePath)) {
            throw new FileNotFoundException(sprintf(FileNotFoundException::FILE_NOT_FOUND, $this->filePath));
        }
    }

    /**
     * Exports the processed ODF file.
     */
    public function exportTo(ExporterInterface $exporter, array $exportParams = []): self
    {
        try {
            $this->validateStateForExport($exporter);

            if (!$this->fileIsCompiled) {
                $this->compile();
            }

            $file = $this->workingDir . DIRECTORY_SEPARATOR . basename($this->filePath);

            if (!file_exists($file)) {
                throw new FileNotFoundException(sprintf(FileNotFoundException::FILE_NOT_FOUND, $file));
            }
            $job = new ExporterJob(
                exportId: $this->generateId('export_'),
                exporterName: $exporter->exporterName,
                jobId: $this->processId,
                action: $exporter->action,
                file: $file,
            );
            $this->exporterResults->set($exporter->exporterName, $exporter->processFile($job, $exportParams));

        } catch (Throwable $e) {
            $this->exporterErrors[$exporter->exporterName] = $e->getMessage();
        }

        return $this;
    }

    /**
     * Valida el estado antes de exportar
     */
    private function validateStateForExport(ExporterInterface $exporter): void
    {
        if (!$this->fileIsLoaded) {
            throw new ValidationException(
                sprintf(ValidationException::ACTION_BEFORE_ACTION, 'El archivo', 'ser cargado', 'exportar')
            );
        }

        if ($exporter->exporterName === '') {
            throw new ValidationException(sprintf(ValidationException::VALUE_NOT_SET, "exporterName"));
        }
    }

    /**
     * Compiles the processed ODF file.
     */
    public function compile(): self
    {
        if (!$this->fileIsLoaded) {
            throw new ValidationException(
                sprintf(ValidationException::ACTION_BEFORE_ACTION, 'El archivo', 'ser cargado', 'compilar')
            );
        }

        try {
            $this->fileContainer->saveFile();
            $this->fileIsCompiled = true;
            $this->finishedAt = microtime(true);
            return $this;
        } catch (Throwable $e) {
            throw new CompilationException(
                CompilationException::DEFAULT_MESSAGE . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Cleans up the working directory.
     */
    public function cleanUpWorkingDir(): void
    {
        if (!isset($this->workingDir) || $this->workingDir === '') {
            return;
        }

        if (!file_exists($this->workingDir)) {
            return;
        }

        if (!is_dir($this->workingDir)) {
            return;
        }

        $this->deleteFileOrDirectory($this->workingDir);
    }

    /**
     * Limpieza silenciosa
     */
    private function silentCleanup(): void
    {
        try {
            $this->cleanUpWorkingDir();
        } catch (Throwable) {
            // Ignorar errores en destructor
        }
    }

    /**
     * Elimina recursivamente un archivo o directorio
     */
    private function deleteFileOrDirectory(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (!is_dir($path)) {
            @unlink($path);
            return;
        }

        $files = @scandir($path);
        if ($files === false) {
            return;
        }

        $files = array_diff($files, ['.', '..']);

        foreach ($files as $file) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;

            if (is_dir($fullPath)) {
                $this->deleteFileOrDirectory($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }

    /**
     * Checks if there are any errors from exporters.
     */
    public function hasExporterErrors(): bool
    {
        return $this->exporterErrors !== [];
    }

    public function getExporterErrors(): array
    {
        return $this->exporterErrors;
    }

    public function getExporterResults(): array
    {
        return $this->exporterResults->toArray();
    }

    public function isFileLoaded(): bool
    {
        return $this->fileIsLoaded;
    }

    public function isFileCompiled(): bool
    {
        return $this->fileIsCompiled;
    }

    public function getResult(): array
    {
        if (!$this->fileIsCompiled) {
            return [];
        }
        return [
            'processId' => $this->processId,
            'template' => $this->filePath,
            'exporters' => $this->exporterResults->keys(),
            'files' => $this->exporterResults->getFiles(),
            'errors' => $this->exporterErrors,
            'startedAt' => $this->startedAt,
            'finishedAt' => $this->finishedAt,
            'duration' => $this->finishedAt * 1000 - $this->startedAt * 1000,
        ];
    }
}