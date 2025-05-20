<?php

/**
 * Represents an ODT file with its corresponding XML parts.
 */

namespace Tabula17\Satelles\Odf;

use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Xml\XmlPart;
use ZipArchive;

/**
 * Handles ODT file operations including loading, modifying, and saving XML components,
 * as well as adding images and managing the manifest.
 */
class OdfContainer implements OdfContainerInterface
{

    private const string PICTURES_PATH = 'Pictures/';

    /**
     * @var XmlPart
     */
    private XmlPart $manifestXml;
    /**
     * @var XmlPart
     */
    private XmlPart $stylesXml;
    /**
     * @var XmlPart
     */
    private XmlPart $settingsXml;
    /**
     * @var XmlPart
     */
    private XmlPart $contentXml;

    private ZipArchive $zip;
    private string $file;
    private bool $zipOpened = false;
    private bool $coroutine;
    private $parts;


    /**
     * @param ZipArchive $zipHandler
     */
    public function __construct(ZipArchive $zipHandler)
    {
        $this->zip = $zipHandler;
    }

    public function __destruct()
    {
        if ($this->zipOpened) {
            // var_export($this->zip);
            $this->zip->close();
        }
    }

    public function getPicturesFolder(): string
    {
        return XmlMemberPath::PICTURES->value;
    }

    public function loadFile(string $file): void
    {
        /*
        if ($this->zip->open($file) !== true) {
            throw new RuntimeException("Error while Opening the file '$file' - Check your odf file");
        }
        foreach (XmlMemberPath::cases() as $member) {
            if ($member->name() === 'pictures') {
                continue;
            }
        //foreach (XmlMember::cases() as $memberName) {
            $path =$member->value;
            if (($xml = $this->zip->getFromName($path)) === false) {
                throw new RuntimeException("Nothing to parse - check that the $path file is correctly formed");
            }
            $part = $member->name() . 'Xml';
            $this->$part = new XmlPart($xml);
        }*/
        //$this->zip->close();
        $this->file = $file;
        foreach (XmlMemberPath::cases() as $member) {
            if ($member->name() === 'pictures' || $member->name() === 'settings') {
                continue;
            }
            $this->loadPart($member);
        }
    }

    public function loadPart(XmlMemberPath $part): void
    {
        if ($part->name() === 'pictures') {
            return;
        }
        $this->ensureZipOpened();
        $path = $part->value;
        if (($xml = $this->zip->getFromName($path)) === false) {
            throw new RuntimeException("Nothing to parse - check that the $path file is correctly formed");
        }
        // $partName = $part->name() . 'Xml';
        // $this->$partName = new XmlPart($xml);
        $this->parts[$part->name()] = new XmlPart($xml);
    }

    public function getPart(XmlMemberPath $part): ?XmlPart
    {
        if ($part->name() === 'pictures') {
            return null;
        }
        return $this->parts[$part->name()];
    }

    /**
     * @param string $fileName
     * @param $mime
     * @return void
     */
    public function registerFileInManifest(string $fileName, $mime): void
    {
        $this->getPart(XmlMemberPath::MANIFEST)->addChild(
            'manifest:file-entry manifest:full-path="' . XmlMemberPath::PICTURES->value . $fileName . '" manifest:media-type="' . $mime . '"'
        );
    }


    private function addStreamImage(string $imgPath, ?string $name = null): void
    {
        $this->ensureZipOpened();
        $stream = fopen($imgPath, 'rb');
        $fileName = $name ?? basename($imgPath);
        $this->zip->addFromString(
            XmlMemberPath::PICTURES->value . $fileName,
            stream_get_contents($stream)
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
     * @throws RuntimeException If the ODT file cannot be opened.
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
     * @throws RuntimeException If the ODT file cannot be opened.
     */
    public function addImages(array $imgPaths, ?array $name = null): void
    {

        foreach ($imgPaths as $key => $imgPath) {
            $fileName = $name[$key] ?? basename($imgPath);
            $this->addStreamImage($imgPath, $fileName);
        }


    }

    public function saveFile(): void
    {
        if (empty($this->file)) {
            throw new RuntimeException("No hay archivo cargado");
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

    private function ensureZipOpened(): void
    {
        if (!$this->zipOpened) {
            if ($this->zip->open($this->file) !== true) {
                throw new RuntimeException("Error while Opening the file '$this->file' - Check your odf file");
            }
            $this->zipOpened = true;
        }
    }

    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_.]/', '', $name);
    }
}