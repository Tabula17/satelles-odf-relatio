<?php

namespace Tabula17\Satelles\Odf;


use Tabula17\Satelles\Odf\Exception\RuntimeException;
use Tabula17\Satelles\Xml\XmlPart;

/**
 * Handles ODT file operations including loading, modifying, and saving XML components,
 * as well as adding images and managing the manifest.
 */
interface  OdfContainerInterface
{
    /**
     * @return string
     */
    public function getPicturesFolder(): string;

    /**
     * @param string $file
     */
    public function loadFile(string $file);

    /**
     * @param XmlMemberPath $part
     */
    public function loadPart(XmlMemberPath $part);

    /**
     * @param XmlMemberPath $part
     * @return XmlPart|null
     * @throws RuntimeException
     *
     * This method retrieves a specific XML part from the ODF container.
     * It uses the XmlMemberPath to identify which part to load and returns the corresponding XmlPart object.
     *
     * If the specified part is not found, it returns null.
     */
    public function getPart(XmlMemberPath $part): ?XmlPart;
    /**
     * @param string $fileName
     * @param $mime
     */
    public function registerFileInManifest(string $fileName, $mime);

    /**
     * Adds an image file to the ODT file by including it in the 'Pictures' directory inside the archive.
     *
     * @param string $imgPath The path to the image file that should be added.
     * @param string|null $name An optional name for the image file within the archive.
     *                          If not provided, the basename of the $imgPath will be used.
     * @throws RuntimeException If the ODT file cannot be opened.
     */
    public function addImage(string $imgPath, ?string $name = null);

    /**
     * Adds multiple image files to the ODT file by including them in the 'Pictures' directory inside the archive.
     *
     * @param array $imgPaths An array of paths to the image files that should be added.
     * @param array|null $name An optional array of names for the image files within the archive.
     *                          If not provided, the basename of each $imgPath will be used.
     * @throws RuntimeException If the ODT file cannot be opened.
     */
    public function addImages(array $imgPaths, ?array $name = null);

    public function saveFile();
}