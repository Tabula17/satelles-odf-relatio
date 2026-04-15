<?php

declare(strict_types=1);

namespace Tabula17\Satelles\Odf\File;

use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\ValidationException;
use Tabula17\Satelles\Odf\Exception\XmlProcessException;
use Tabula17\Satelles\Odf\OdfContainerInterface;
use Tabula17\Satelles\Odf\XmlMemberPath;
use Tabula17\Satelles\Xml\XmlPart;
use ZipArchive;

/**
 * OdfContainer - Optimizado para alto rendimiento
 *
 * Características:
 * - Carga lazy de partes XML
 * - Cache de partes procesadas
 * - Compatible con Swoole (sin estado global)
 */
class OdfContainer implements OdfContainerInterface
{
    private ZipArchive $zip;
    private string $file;
    private bool $zipOpened = false;
    private array $parts = [];

    // Cache de contenido para evitar re-procesamiento
    private array $contentCache = [];

    // Flag para indicar si el archivo fue modificado
    private bool $isModified = false;

    public function __construct(ZipArchive $zipHandler)
    {
        $this->zip = $zipHandler;
    }

    public function __destruct()
    {
        if ($this->zipOpened) {
            $this->zip->close();
            $this->zipOpened = false;
        }
    }

    public function getPicturesFolder(): string
    {
        return XmlMemberPath::PICTURES->value;
    }

    /**
     * Loads the ODT file - versión optimizada con carga lazy
     */
    public function loadFile(string $file): void
    {
        $this->file = $file;
        $this->parts = [];
        $this->contentCache = [];
        $this->isModified = false;

        // Cargar solo partes esenciales bajo demanda
        // Las demás se cargarán cuando se necesiten
    }

    /**
     * Loads a specific part (con cache)
     */
    public function loadPart(XmlMemberPath $part): void
    {
        if ($part === XmlMemberPath::PICTURES) {
            return;
        }

        $partName = $part->name();

        // Verificar cache primero
        if (isset($this->parts[$partName])) {
            return;
        }

        $this->ensureZipOpened();
        $path = $part->value;

        // Leer contenido una vez y cachear
        if (!isset($this->contentCache[$path])) {
            $content = $this->zip->getFromName($path);
            if ($content === false) {
                throw new XmlProcessException(sprintf(XmlProcessException::EMPTY_PARSE_ERROR, $path));
            }
            $this->contentCache[$path] = $content;
        }

        try {
            $this->parts[$partName] = new XmlPart($this->contentCache[$path]);
        } catch (\Exception $e) {
            throw new XmlProcessException(sprintf(XmlProcessException::FAILED_TO_PARSE_XML, $path), 0, $e);
        }
    }

    /**
     * Gets a specific part (carga lazy)
     * @throws XmlProcessException
     */
    public function getPart(XmlMemberPath $part): ?XmlPart
    {
        if ($part === XmlMemberPath::PICTURES) {
            return null;
        }

        $partName = $part->name();

        // Carga lazy si no está en memoria
        if (!isset($this->parts[$partName])) {
            $this->loadPart($part);
        }

        return $this->parts[$partName] ?? null;
    }

    /**
     * Registers a file in the manifest (batch processing)
     */
    public function registerFileInManifest(string $fileName, string|null $mime): void
    {
        $manifest = $this->getPart(XmlMemberPath::MANIFEST);
        if ($manifest !== null) {
            $manifest->addChild(
                sprintf(
                    'manifest:file-entry manifest:full-path="%s%s" manifest:media-type="%s"',
                    XmlMemberPath::PICTURES->value,
                    $fileName,
                    $mime
                )
            );
            $this->isModified = true;
        }
    }

    /**
     * Adds a stream image (optimizado con buffer)
     * @throws XmlProcessException
     */
    private function addStreamImage(string $imgPath, ?string $name = null): void
    {
        $this->ensureZipOpened();
        $fileName = $name ?? basename($imgPath);

        // Usar file_get_contents es más rápido que fopen/fread para archivos pequeños
        $content = @file_get_contents($imgPath);
        if ($content === false) {
            throw new XmlProcessException(sprintf(FileException::CANT_LOAD_STREAM, $imgPath));
        }

        $this->zip->addFromString(
            XmlMemberPath::PICTURES->value . $fileName,
            $content
        );
        $this->isModified = true;
    }

    /**
     * @throws XmlProcessException
     */
    public function addImage(string $imgPath, ?string $name = null): void
    {
        $this->addStreamImage($imgPath, $name);
    }

    /**
     * Adds multiple images (procesamiento batch)
     * @throws XmlProcessException
     */
    public function addImages(array $imgPaths, ?array $names = null): void
    {
        if (empty($imgPaths)) {
            return;
        }

        $this->ensureZipOpened();

        foreach ($imgPaths as $key => $imgPath) {
            $fileName = $names[$key] ?? basename($imgPath);

            $content = @file_get_contents($imgPath);
            if ($content === false) {
                continue; // Skip files that can't be read
            }

            $this->zip->addFromString(
                XmlMemberPath::PICTURES->value . $fileName,
                $content
            );
        }

        $this->isModified = true;
    }

    /**
     * Saves the file - solo si hubo modificaciones
     * @throws XmlProcessException|ValidationException
     */
    public function saveFile(): void
    {
        if (empty($this->file)) {
            throw new XmlProcessException(XmlProcessException::XML_NOT_LOADED);
        }

        if (!$this->isModified && empty($this->parts)) {
            return; // Nada que guardar
        }

        $this->ensureZipOpened();

        try {
            foreach ($this->parts as $name => $part) {
                $memberPath = XmlMemberPath::fromName($name);
                $this->zip->addFromString(
                    $memberPath,
                    $part->asXml()
                );
            }
        } finally {
            $this->zip->close();
            $this->zipOpened = false;
            $this->isModified = false;
        }
    }

    /**
     * Ensures ZIP is opened (con reintento para concurrencia)
     */
    private function ensureZipOpened(): void
    {
        if ($this->zipOpened) {
            return;
        }

        $maxRetries = 3;
        $retry = 0;

        while ($retry < $maxRetries) {
            $result = $this->zip->open($this->file);
            if ($result === true) {
                $this->zipOpened = true;
                return;
            }

            $retry++;
            if ($retry < $maxRetries) {
                usleep(10000 * $retry); // 10ms, 20ms, 30ms
            }
        }

        throw new XmlProcessException(sprintf(XmlProcessException::FAILED_TO_OPEN_FILE, $this->file));
    }

    /**
     * Limpia el cache de contenido (útil en workers de larga duración)
     */
    public function clearCache(): void
    {
        $this->contentCache = [];
    }

    /**
     * Verifica si el archivo fue modificado
     */
    public function isModified(): bool
    {
        return $this->isModified;
    }
}