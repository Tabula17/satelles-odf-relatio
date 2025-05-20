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
    public function getPicturesFolder(): string;

    public function loadFile(string $file): void;

    public function loadPart(XmlMemberPath $part): void;
    public function getPart(XmlMemberPath $part): ?XmlPart;
    /**
     * @param string $fileName
     * @param $mime
     * @return void
     */
    public function registerFileInManifest(string $fileName, $mime): void;

    /**
     * Adds an image file to the ODT file by including it in the 'Pictures' directory inside the archive.
     *
     * @param string $imgPath The path to the image file that should be added.
     * @param string|null $name An optional name for the image file within the archive.
     *                          If not provided, the basename of the $imgPath will be used.
     * @return void Returns the current instance for method chaining.
     * @throws RuntimeException If the ODT file cannot be opened.
     */
    public function addImage(string $imgPath, ?string $name = null): void;

    /**
     * Adds multiple image files to the ODT file by including them in the 'Pictures' directory inside the archive.
     *
     * @param array $imgPaths An array of paths to the image files that should be added.
     * @param array|null $name An optional array of names for the image files within the archive.
     *                          If not provided, the basename of each $imgPath will be used.
     * @return void Returns the current instance for method chaining.
     * @throws RuntimeException If the ODT file cannot be opened.
     */
    public function addImages(array $imgPaths, ?array $name = null): void;

    public function saveFile(): void;
}