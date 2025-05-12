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
class OdfContainer
{
    public const array XML_MEMBERS = [
        'manifest',
        'styles',
        'settings',
        'content'
    ];
    private const array XML_PATHS = [
        'content' => 'content.xml',
        'styles' => 'styles.xml',
        'manifest' => 'META-INF/manifest.xml',
        'settings' => 'settings.xml'
    ];
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

    /**
     * @param ZipArchive $zipHandler
     */
    public function __construct(ZipArchive $zipHandler)
    {
        $this->zip = $zipHandler;
    }

    public function getPicturesFolder(): string
    {
        return self::PICTURES_PATH;
    }

    /**
     * @param $member
     * @return string|null
     */
    public static function getPath($member): ?string
    {
        return self::XML_PATHS[$member] ?? null;
    }

    /**
     * @return XmlPart
     */
    public function getManifestXml(): XmlPart
    {
        return $this->manifestXml;
    }
    /**
     * @return XmlPart
     */
    public function getStylesXml(): XmlPart
    {
        return $this->stylesXml;
    }

    /**
     * @return XmlPart
     */
    public function getSettingsXml(): XmlPart
    {
        return $this->settingsXml;
    }

    /**
     * @return XmlPart
     */
    public function getContentXml(): XmlPart
    {
        return $this->contentXml;
    }

     public function loadFile(string $file): void
    {
        if ($this->zip->open($file) !== true) {
            throw new RuntimeException("Error while Opening the file '$file' - Check your odf file");
        }
        $odfMembers = self::XML_MEMBERS;
        foreach ($odfMembers as $memberName) {
            $path = self::getPath($memberName);
            if (($xml = $this->zip->getFromName($path)) === false) {
                throw new RuntimeException("Nothing to parse - check that the $path file is correctly formed");
            }
            $part = $memberName . 'Xml';
            $this->$part = new XmlPart($xml);
        }
        $this->zip->close();
        $this->file = $file;
    }    /**
 * @param string $fileName
 * @param $mime
 * @return void
 */
    public function registerFileInManifest(string $fileName, $mime): void
    {
        $this->manifestXml->addChild(
            'manifest:file-entry manifest:full-path="' . self::PICTURES_PATH . $fileName . '" manifest:media-type="' . $mime . '"'
        );
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
        if ($this->zip->open($this->file) !== true) {
            throw new RuntimeException("Error while Opening the file '$this->file' - Check your odf file");
        }
        $fileName = $name ?? basename($imgPath);
        $this->zip->addFile($imgPath, self::PICTURES_PATH . $fileName);
        $this->zip->close();
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
        if ($this->zip->open($this->file) !== true) {
            throw new RuntimeException("Error while Opening the file '$this->file' - Check your odf file");
        }
        foreach ($imgPaths as $key => $imgPath) {
            $this->zip->addFile($imgPath, self::PICTURES_PATH . ($name[$key] ?? basename($imgPath)));
        }
        $this->zip->close();

    }
    public function saveFile(): void{
        if (!$this->file||$this->zip->open($this->file) !== true) {
            throw new RuntimeException("Error while Opening the file '$this->file' - Check your odf file");
        }
        $odfMembers = self::XML_MEMBERS;
        foreach ($odfMembers as $memberName) {
            $path = self::getPath($memberName);
            $part = $memberName . 'Xml';
         //   echo $path, PHP_EOL, $memberName, PHP_EOL, $part, PHP_EOL;


            $this->zip->addFromString($path, $this->$part->asXml());
        }
        $this->zip->close();
    }
}