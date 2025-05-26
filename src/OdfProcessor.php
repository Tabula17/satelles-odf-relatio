<?php

namespace Tabula17\Satelles\Odf;

use ErrorException;
use Exception;
use Tabula17\Satelles\Odf\Exception\CompilationException;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\NonWritableFileException;
use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;
use Tabula17\Satelles\Odf\Exception\ValidationException;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;
use Tabula17\Satelles\Odf\Template\XmlProcessor;
use Throwable;

/**
 * Class OdfProcessor
 *
 * Processes and manipulates ODF (Open Document Format) files with functionality
 * for rendering templates, compiling, exporting, and managing working directories.
 * Handles various aspects of ODF file processing using provided data and exporters.
 */
class OdfProcessor
{
    private const string TEMP_PREFIX = 'j17';
    private const int DIRECTORY_PERMISSIONS = 0777;

    public string $workingDir {
        get => $this->workingDir;
    }
    private OdfContainer $fileContainer;
    private string $filePath;
    private bool $fileIsLoaded = false;
    private bool $fileIsCompiled = false;
    private DataRenderer $renderer;
    private XmlProcessor $xmlProcessor;
    public array $exporterResults = [];
    public array $exporterErrors = [];

    /**
     * Creates a new OdfProcessor instance.
     *
     * @param string $odfFilePath Path to the ODF file to process
     * @param string $baseDir Base directory for temporary files
     * @param OdfContainer $fileContainer Container for the ODF file
     * @param DataRenderer|null $renderer Data renderer for template processing
     *
     * @throws FileNotFoundException If the ODF file does not exist
     * @throws NonWritableFileException If the base directory is not writable
     * @throws ValidationException If any parameter is invalid
     * @throws FileException
     */
    public function __construct(
        string                 $odfFilePath,
        string                 $baseDir,
        OdfContainer           $fileContainer,
        ?DataRenderer          $renderer = null,
        ?XmlProcessorInterface $xmlProcessor = null
    )
    {
        // Validate ODF file path
        if (empty($odfFilePath)) {
            throw new ValidationException(ValidationException::EMPTY_PATH);
        }
        if (!file_exists($odfFilePath)) {
            throw new FileNotFoundException(sprintf(FileNotFoundException::FILE_NOT_FOUND, $odfFilePath));
        }
        if (!is_readable($odfFilePath)) {
            throw new FileException(sprintf(FileException::CANT_READ, $odfFilePath));
        }

        // Validate base directory and set working directory
        $this->setWorkingDirectory($baseDir);

        // Set properties
        $this->filePath = $odfFilePath;
        $this->renderer = $renderer ?? new DataRenderer(null, null);
        $this->fileContainer = $fileContainer;
        $this->xmlProcessor = $xmlProcessor ?? new XmlProcessor($this->renderer, $this->fileContainer);
    }

    /**
     * Processes the ODF file with the provided data.
     *
     * @param array $data Data to use for template processing
     * @param string|null $alias Optional alias for data access
     *
     * @return self For method chaining
     *
     * @throws ValidationException If the data is invalid
     * @throws RuntimeException If there's an error during processing
     * @throws StrictValueConstraintException|FileNotFoundException
     */
    public function process(array $data, ?string $alias = null): self
    {
        // Validate data
        if (empty($data)) {
            throw new ValidationException(sprintf(ValidationException::EMPTY_FIELD, 'La matríz data'));
        }
        // Validate alias if provided
        if ($alias !== null && !is_string($alias)) {
            throw new ValidationException(sprintf(ValidationException::ONLY_STRING_OR_NULL, 'Alias', $alias));//"Alias must be a string or null";
        }
        if ($alias !== null && empty($alias)) {
            throw new ValidationException(sprintf(ValidationException::EMPTY_STRING, 'Alias'));//"Alias cannot be an empty string";
        }

        if ($alias !== null && !preg_match('/^[a-zA-Z0-9_]+$/', $alias)) {
            throw new ValidationException(sprintf(ValidationException::CONTAINS_INVALID_CHARS, $alias));//"Alias contains invalid characters. Only alphanumeric characters and underscores are allowed.";
        }

        // Load file if not already loaded
        if (!$this->fileIsLoaded) {
            $this->loadFile();
        }

        $this->renderer->allData = $data;
        $this->fileIsCompiled = false;

        $this->processXmlFiles($data, $alias);

        return $this;
    }

    /**
     * @param array $data
     * @param string|null $alias
     * @return void
     * @throws StrictValueConstraintException
     */
    private function processXmlFiles(array $data, ?string $alias): void
    {
        $this->xmlProcessor->processTemplate($this->fileContainer->getPart(XmlMemberPath::CONTENT), $data, $alias);
        $this->xmlProcessor->processTemplate($this->fileContainer->getPart(XmlMemberPath::STYLES), $data, $alias);
    }

    /**
     * Sets the working directory for temporary files.
     * @param string $baseDir
     * @return void
     * @throws NonWritableFileException
     * @throws ErrorException
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
     * @param string $dir
     * @return bool
     */
    private function isValidDirectory(string $dir): bool
    {
        return realpath($dir) && is_dir($dir) && is_writable($dir);
    }

    /**
     * Creates a unique working directory within the specified base directory.
     * @throws NonWritableFileException
     * @throws ErrorException
     */
    private function createUniqueWorkingDirectory(string $baseDir): string
    {
        $workingDir = $baseDir . DIRECTORY_SEPARATOR . uniqid(self::TEMP_PREFIX, true);

        try {
            if (!mkdir($workingDir, self::DIRECTORY_PERMISSIONS, true) && !is_dir($workingDir)) {
                throw new NonWritableFileException(
                    sprintf(FileException::CANT_CREATE, $workingDir)
                );
            }
        } catch (ErrorException $e) {
            if (str_contains($e->getMessage(), 'File exists')) {
                // Manejar la condición de carrera: el directorio ya existe
                // Puedes intentar generar un nuevo nombre o lanzar una excepción
                throw new NonWritableFileException(
                    sprintf(FileException::CANT_OVERWRITE, $workingDir)
                );
            }

// Relanzar la excepción si no es la condición de carrera
            throw $e;
        }

        return $workingDir;
    }

    /**
     * Loads the ODF file into memory.
     *
     * @return self For method chaining
     *
     * @throws ValidationException If the internal state is invalid
     * @throws RuntimeException If there's an error during file loading
     * @throws FileNotFoundException
     */
    public function loadFile(): self
    {
        // Validate internal state
        if (empty($this->filePath)) {
            throw new ValidationException(sprintf(ValidationException::VALUE_NOT_SET, "filePath"));
        }

        if (empty($this->workingDir)) {
            throw new ValidationException(sprintf(ValidationException::VALUE_NOT_SET, "workingDir"));
        }

        if (!file_exists($this->filePath)) {
            throw new FileNotFoundException(sprintf(FileNotFoundException::FILE_NOT_FOUND, $this->filePath));
        }

        try {
            $odfFile = $this->workingDir . DIRECTORY_SEPARATOR . basename($this->filePath);
            if (!copy($this->filePath, $odfFile)) {
                $error = error_get_last();
                throw new FileException(sprintf(FileException::CANT_COPY, $this->filePath, $odfFile) . ':' . PHP_EOL . $error['message']);
            }

            $this->fileIsCompiled = false;
            try {
                $this->fileContainer->loadFile($odfFile);
            } catch (\Exception $e) {
                throw new RuntimeException(sprintf(RuntimeException::FAILED_TO_LOAD, $odfFile) . ': ' . $e->getMessage(), 0, $e);
            }
            $this->fileIsLoaded = true;

            return $this;
        } catch (Exception $e) {
            throw new RuntimeException(sprintf(RuntimeException::ACTION_ERROR, 'opening', $odfFile) . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Exports the processed ODF file using the provided exporter.
     *
     * @param ExporterInterface $exporter Exporter to use for exporting the file
     *
     * @return self For method chaining
     */
    public function exportTo(ExporterInterface $exporter): self
    {
        try {
            // Validate internal state
            if (!$this->fileIsLoaded) {
                throw new ValidationException(sprintf(ValidationException::ACTION_BEFORE_ACTION, 'El archivo', 'ser cargado', 'exportar'));
            }

            // Validate exporter
            if (empty($exporter->exporterName)) {
                throw new ValidationException(sprintf(ValidationException::VALUE_NOT_SET, "exporterName"));
            }

            // Compile if not already compiled
            if (!$this->fileIsCompiled) {
                $this->compile();
            }

            // Export the file
            $file = $this->workingDir . DIRECTORY_SEPARATOR . basename($this->filePath);

            if (!file_exists($file)) {
                throw new FileNotFoundException(sprintf(FileNotFoundException::FILE_NOT_FOUND, $file));
            }
            $this->exporterResults[$exporter->exporterName] = $exporter->processFile($file);


        } catch (Throwable $e) {
            // Handle exceptions and store error messages
            $this->exporterErrors[$exporter->exporterName] = $e->getMessage();
        }
        return $this;
    }

    /**
     * Compiles the processed ODF file.
     *
     * @return self For method chaining
     *
     * @throws ValidationException If the file is not loaded
     * @throws CompilationException If there's an error during compilation
     */
    public function compile(): self
    {
        // Validate internal state
        if (!$this->fileIsLoaded) {
            throw new ValidationException(sprintf(ValidationException::ACTION_BEFORE_ACTION, 'El archivo', 'ser cargado', 'compilar'));
        }

        try {
            $this->fileContainer->saveFile();
            $this->fileIsCompiled = true;
            return $this;
        } catch (Exception $e) {
            throw new CompilationException(CompilationException::DEFAULT_MESSAGE . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Cleans up the working directory by deleting all temporary files.
     *
     * @throws ValidationException If the working directory is not set
     * @throws NonWritableFileException If the working directory cannot be deleted
     */
    public function cleanUpWorkingDir(): void
    {
        // Validate internal state
        if (empty($this->workingDir)) {
            throw new ValidationException(sprintf(ValidationException::VALUE_NOT_SET, "workingDir"));
        }

        if (!file_exists($this->workingDir)) {
            // If the directory doesn't exist, there's nothing to clean up
            return;
        }

        if (!is_dir($this->workingDir)) {
            throw new ValidationException(sprintf(ValidationException::EXISTS_BUT_NOT_DIR, $this->workingDir));
        }

        if (!is_writable($this->workingDir)) {
            throw new NonWritableFileException(sprintf(NonWritableFileException::NON_WRITABLE_DIR, $this->workingDir));
        }

        $this->deleteFileOrDirectory($this->workingDir);
    }

    private function deleteFileOrDirectory(string $filename): void
    {
        if (!file_exists($filename)) {
            return;
        }

        if (!is_dir($filename)) {
            unlink($filename);
            return;
        }

        $files = array_diff(scandir($filename), ['.', '..']);
        foreach ($files as $file) {
            $path = $filename . DIRECTORY_SEPARATOR . $file;
            if (!is_writable($path)) {
                return;
            }
            if (is_dir($path)) {
                $this->deleteFileOrDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($filename);
    }

    /**
     * Checks if there are any errors from the exporters that have been used.
     * @return bool
     */
    public function hasExporterErrors(): bool
    {
        return !empty($this->exporterErrors);
    }

    /**
     * Gets the errors from all exporters that have been used.
     *
     * @return array Associative array of exporter errors, keyed by exporter name
     */
    public function getExporterErrors(): array
    {
        return $this->exporterErrors;
    }

    /**
     * Gets the results from all exporters that have been used.
     *
     * @return array Associative array of exporter results, keyed by exporter name
     */
    public function getExporterResults(): array
    {
        return $this->exporterResults;
    }

    /**
     * Checks if the file has been loaded.
     *
     * @return bool True if the file has been loaded, false otherwise
     */
    public function isFileLoaded(): bool
    {
        return $this->fileIsLoaded;
    }
}
