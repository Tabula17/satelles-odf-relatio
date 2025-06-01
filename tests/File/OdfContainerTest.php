<?php

namespace Tabula17\Satelles\Odf\Tests\File;

use PHPUnit\Framework\TestCase;
use Tabula17\Satelles\Odf\Exception\XmlProcessException;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\XmlMemberPath;
use ZipArchive;

class OdfContainerTest extends TestCase
{
    private string $tempDir;
    private string $testFile;
    private OdfContainer $container;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/odf_test_' . uniqid();
        mkdir($this->tempDir);
        
        // Crear archivo ODT de prueba
        $this->testFile = $this->tempDir . '/test.odt';
        $zip = new ZipArchive();
        $zip->open($this->testFile, ZipArchive::CREATE);
        $zip->addFromString('content.xml', '<?xml version="1.0"?><office:document-content></office:document-content>');
        $zip->addFromString('styles.xml', '<?xml version="1.0"?><office:document-styles></office:document-styles>');
        $zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0"?><manifest:manifest></manifest:manifest>');
        $zip->close();

        $this->container = new OdfContainer(new ZipArchive());
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testLoadFile(): void
    {
        $this->container->loadFile($this->testFile);
        
        $this->assertNotNull($this->container->getPart(XmlMemberPath::CONTENT));
        $this->assertNotNull($this->container->getPart(XmlMemberPath::STYLES));
        $this->assertNotNull($this->container->getPart(XmlMemberPath::MANIFEST));
    }

    public function testLoadFileWithInvalidXml(): void
    {
        $invalidFile = $this->tempDir . '/invalid.odt';
        $zip = new ZipArchive();
        $zip->open($invalidFile, ZipArchive::CREATE);
        $zip->addFromString('content.xml', 'Invalid XML');
        $zip->close();

        $this->expectException(XmlProcessException::class);
        $this->container->loadFile($invalidFile);
    }

    public function testRegisterFileInManifest(): void
    {
        $this->container->loadFile($this->testFile);
        
        $this->container->registerFileInManifest('test.png', 'image/png');
        
        $manifest = $this->container->getPart(XmlMemberPath::MANIFEST);
        $result = $manifest->asXml();
        $this->assertStringContainsString('Pictures/test.png', $result);
        $this->assertStringContainsString('image/png', $result);
    }

    public function testAddImage(): void
    {
        $this->container->loadFile($this->testFile);
        
        // Crear imagen temporal de prueba
        $testImage = $this->tempDir . '/test.png';
        file_put_contents($testImage, 'fake image data');
        
        $this->container->addImage($testImage);
        
        // Guardar y verificar
        $this->container->saveFile();
        
        // Verificar que la imagen se agregó al archivo
        $zip = new ZipArchive();
        $zip->open($this->testFile);
        $this->assertNotFalse($zip->getFromName('Pictures/' . basename($testImage)));
        $zip->close();
    }

    public function testAddMultipleImages(): void
    {
        $this->container->loadFile($this->testFile);
        
        // Crear imágenes temporales de prueba
        $testImage1 = $this->tempDir . '/test1.png';
        $testImage2 = $this->tempDir . '/test2.png';
        file_put_contents($testImage1, 'fake image 1');
        file_put_contents($testImage2, 'fake image 2');
        
        $this->container->addImages([$testImage1, $testImage2]);
        
        // Guardar y verificar
        $this->container->saveFile();
        
        // Verificar que las imágenes se agregaron al archivo
        $zip = new ZipArchive();
        $zip->open($this->testFile);
        $this->assertNotFalse($zip->getFromName('Pictures/' . basename($testImage1)));
        $this->assertNotFalse($zip->getFromName('Pictures/' . basename($testImage2)));
        $zip->close();
    }

    public function testSaveFile(): void
    {
        $this->container->loadFile($this->testFile);
        
        // Modificar contenido
        $content = $this->container->getPart(XmlMemberPath::CONTENT);
        $content->addChild('test:element', 'Test Content');
        
        $this->container->saveFile();
        
        // Verificar que los cambios se guardaron
        $zip = new ZipArchive();
        $zip->open($this->testFile);
        $savedContent = $zip->getFromName('content.xml');
        $zip->close();
        
        $this->assertStringContainsString('Test Content', $savedContent);
    }

    public function testGetPicturesFolder(): void
    {
        $this->assertEquals('Pictures/', $this->container->getPicturesFolder());
    }
}
