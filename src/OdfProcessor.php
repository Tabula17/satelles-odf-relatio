<?php
namespace Tabula17\Satelles\Odf;

use Exception;
use Tabula17\Satelles\Odf\Exception\CompilationException;
use Tabula17\Satelles\Odf\Exception\NonWritableFileException;
use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;
use Tabula17\Satelles\Odf\Template\TemplateProcessor;

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
    private TemplateProcessor $xmlProcessor;
    public array $exporterResults = [] ;

    public function __construct(
        string        $odfFilePath,
        string        $baseDir,
        OdfContainer  $fileContainer,
        ?DataRenderer $renderer = null
    ) {
        $this->setWorkingDirectory($baseDir);
        $this->filePath = $odfFilePath;
        $this->renderer = $renderer ?? new DataRenderer(null, null);
        $this->fileContainer = $fileContainer;
    }

    public function process(array $data, ?string $alias = null): self
    {
        if (!$this->fileIsLoaded) {
            $this->loadFile();
        }

        $this->renderer->allData = $data;
        $this->fileIsCompiled = false;
        $this->xmlProcessor = new TemplateProcessor($this->renderer, $this->fileContainer);

        $this->processXmlFiles($data, $alias);

        return $this;
    }

    private function processXmlFiles(array $data, ?string $alias): void
    {
        $this->xmlProcessor->processTemplate($this->fileContainer->getPart(XmlMemberPath::CONTENT), $data, $alias);
        $this->xmlProcessor->processTemplate($this->fileContainer->getPart(XmlMemberPath::STYLES), $data, $alias);
    }

    private function setWorkingDirectory(string $baseDir): void
    {
        if (!$this->isValidDirectory($baseDir)) {
            throw new NonWritableFileException(
                "El directorio $baseDir no existe o no se puede escribir en él."
            );
        }
        $this->workingDir = $this->createUniqueWorkingDirectory($baseDir);
    }

    private function isValidDirectory(string $dir): bool
    {
        return realpath($dir) && is_dir($dir) && is_writable($dir);
    }

    private function createUniqueWorkingDirectory(string $baseDir): string
    {
        $workingDir = $baseDir . DIRECTORY_SEPARATOR . uniqid(self::TEMP_PREFIX, true);

        if (!file_exists($workingDir)) {
            if (!mkdir($workingDir, self::DIRECTORY_PERMISSIONS, true) && !is_dir($workingDir)) {
                throw new NonWritableFileException(
                    sprintf('No se pudo crear el directorio "%s"', $workingDir)
                );
            }
        } elseif (!is_dir($workingDir)) {
            throw new NonWritableFileException(
                "El path $workingDir ya existe y no es un directorio."
            );
        }

        return $workingDir;
    }

    public function loadFile(): self
    {
        try {
            $odfFile = $this->workingDir . DIRECTORY_SEPARATOR . basename($this->filePath);
            copy($this->filePath, $odfFile);

            $this->fileIsCompiled = false;
            $this->fileContainer->loadFile($odfFile);
            /*$this->fileContainer->loadPart(XmlMemberPath::CONTENT);
            $this->fileContainer->loadPart(XmlMemberPath::STYLES);
            $this->fileContainer->loadPart(XmlMemberPath::MANIFEST);*/
            $this->fileIsLoaded = true;

            return $this;
        } catch (Exception $e) {
            throw new RuntimeException("Error while Opening the file '$odfFile' - Check your odf file");
        }
    }

    public function exportTo(ExporterInterface $exporter): self
    {
        if (!$this->fileIsCompiled) {
            $this->compile();
        }

        $file = $this->workingDir . DIRECTORY_SEPARATOR . basename($this->filePath);
        $this->exporterResults[$exporter->exporterName] = $exporter->processFile($file);

        return $this;
    }

    public function compile(): self
    {
        try {
            $this->fileContainer->saveFile();
            $this->fileIsCompiled = true;
            return $this;
        } catch (Exception $e) {
            throw new CompilationException($e->getMessage());
        }
    }

    public function cleanUpWorkingDir(): void
    {
        $this->deleteFileOrDirectory($this->workingDir);
    }

    private function deleteFileOrDirectory(string $filename): bool
    {
        if (!file_exists($filename)) {
            return true;
        }

        if (!is_dir($filename)) {
            return unlink($filename);
        }

        foreach (scandir($filename) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (!$this->deleteFileOrDirectory($filename . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($filename);
    }

    public function getExporterResults(): array
    {
        return $this->exporterResults;
    }

    public function isFileLoaded(): bool
    {
        return $this->fileIsLoaded;
    }
}