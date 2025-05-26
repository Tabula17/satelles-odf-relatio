<?php

/**
 * Represents an ODT file with its corresponding XML parts.
 */

namespace Tabula17\Satelles\Odf\File;

use Exception;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Odf\Exception\XmlProcessException;
use Tabula17\Satelles\Odf\OdfContainerInterface;
use Tabula17\Satelles\Odf\XmlMemberPath;
use Tabula17\Satelles\Xml\XmlPart;
use ZipArchive;

/**
 * Handles ODT file operations including loading, modifying, and saving XML components,
 * as well as adding images and managing the manifest.
 */
class OdfContainer implements OdfContainerInterface
{
    /**
     * @var ZipArchive
     */
    private ZipArchive $zip;
    /**
     * @var string
     */
    private string $file;
    /**
     * @var bool
     */
    private bool $zipOpened = false;
    /**
     * @var XmlPart[]|null
     */
    private ?array $parts = [];


    /**
     * Constructor for the OdfContainer class.
     * @param ZipArchive $zipHandler
     */
    public function __construct(ZipArchive $zipHandler)
    {
        $this->zip = $zipHandler;
    }

    /**
     * Destructor for the OdfContainer class.
     */
    public function __destruct()
    {
        if ($this->zipOpened) {
            // var_export($this->zip);
            $this->zip->close();
        }
    }

    /**
     * Returns the path to the 'Pictures' directory within the ODT file.
     * @return string
     */
    public function getPicturesFolder(): string
    {
        return XmlMemberPath::PICTURES->value;
    }

    /**
     * Loads the ODT file and its XML parts.
     * @param string $file
     * @return void
     * @throws RuntimeException
     */
    public function loadFile(string $file): void
    {
        $this->file = $file;
        foreach (XmlMemberPath::cases() as $member) {
            if ($member->name() === 'pictures' || $member->name() === 'settings') {
                continue;
            }
            $this->loadPart($member);
        }
    }

    /**
     * Loads a specific part of the ODT file based on the provided XmlMemberPath.
     * @param XmlMemberPath $part
     * @return void
     * @throws XmlProcessException
     */
    public function loadPart(XmlMemberPath $part): void
    {
        if ($part->name() === 'pictures') {
            return;
        }
        $this->ensureZipOpened();
        $path = $part->value;
        if (($xml = $this->zip->getFromName($path)) === false) {
            throw new XmlProcessException(sprintf(XmlProcessException::EMPTY_PARSE_ERROR, $path));
        }
        // $partName = $part->name() . 'Xml';
        // $this->$partName = new XmlPart($xml);
        try {
            $xml = new XmlPart($xml);
        } catch (Exception $e) {
            throw new XmlProcessException(sprintf(XmlProcessException::FAILED_TO_PARSE_XML, $path), 0, $e);
        }
        $this->parts[$part->name()] = $xml;
    }

    /**
     * Retrieves a specific part of the ODT file based on the provided XmlMemberPath.
     * @param XmlMemberPath $part
     * @return XmlPart|null
     */
    public function getPart(XmlMemberPath $part): ?XmlPart
    {
        if ($part->name() === 'pictures') {
            return null;
        }
        return $this->parts[$part->name()];
    }

    /**
     * Registers a file in the ODT manifest.
     * @param string $fileName
     * @param $mime
     * @return void
     */
    public function registerFileInManifest(string $fileName, $mime): void
    {
        if ($this->getPart(XmlMemberPath::MANIFEST) !== null) {
            $this->getPart(XmlMemberPath::MANIFEST)->addChild(
                'manifest:file-entry manifest:full-path="' . XmlMemberPath::PICTURES->value . $fileName . '" manifest:media-type="' . $mime . '"'
            );
        }
    }

    /**
     * Adds an image file to the ODT file by reading it as a stream and including it in the 'Pictures' directory inside the archive.
     * @param string $imgPath
     * @param string|null $name
     * @return void
     * @throws XmlProcessException
     */
    private function addStreamImage(string $imgPath, ?string $name = null): void
    {
        $this->ensureZipOpened();
        $stream = fopen($imgPath, 'rb');
        $fileName = $name ?? basename($imgPath);
        $content = stream_get_contents($stream);
        if ($content === false) {
            throw new XmlProcessException(sprintf(FileException::CANT_LOAD_STREAM, $imgPath));
        }
        $this->zip->addFromString(
            XmlMemberPath::PICTURES->value . $fileName,
            $content
        );
        fclose($stream);
    }

    /**
     * Adds an image file to the ODT file by including it in the 'Pictures' directory inside the archive.
     *
     * @param string $imgPath The path to the image file that should be added.
     * @param string|null $name An optional name for the image file within the archive.
     *                          If not provided, the basename of the $imgPath will be used.
     * @return void Returns the current instance for method chaining.
     * @throws XmlProcessException
     */
    public function addImage(string $imgPath, ?string $name = null): void
    {
        $this->addStreamImage($imgPath, $name);
    }

    /**
     * Adds multiple image files to the ODT file by including them in the 'Pictures' directory inside the archive.
     *
     * @param array $imgPaths An array of paths to the image files that should be added.
     * @param array|null $name An optional array of names for the image files within the archive.
     *                          If not provided, the basename of each $imgPath will be used.
     * @return void Returns the current instance for method chaining.
     * @throws XmlProcessException If the ODT file cannot be opened.
     */
    public function addImages(array $imgPaths, ?array $name = null): void
    {

        foreach ($imgPaths as $key => $imgPath) {
            $fileName = $name[$key] ?? basename($imgPath);
            $this->addStreamImage($imgPath, $fileName);
        }


    }

    /**
     * Saves the ODT file by writing all loaded XML parts back into the archive.
     * @return void
     * @throws XmlProcessException
     */
    public function saveFile(): void
    {
        if (empty($this->file)) {
            throw new XmlProcessException(XmlProcessException::XML_NOT_LOADED);
        }
        $this->ensureZipOpened();
        try {
            foreach ($this->parts as $name => $part) {
                $this->zip->addFromString(
                    XmlMemberPath::fromName($name),
                    $part->asXml()
                );
            }
        } finally {
            $this->zip->close();
            $this->zipOpened = false;
        }
    }

    /**
     * Ensures that the ZIP archive is opened before performing any operations on it.
     * @return void
     * @throws XmlProcessException
     */
    private function ensureZipOpened(): void
    {
        if (!$this->zipOpened) {
            if ($this->zip->open($this->file) !== true) {
                throw new XmlProcessException(sprintf(XmlProcessException::FAILED_TO_OPEN_FILE, $this->file));
            }
            $this->zipOpened = true;
        }
    }

}
