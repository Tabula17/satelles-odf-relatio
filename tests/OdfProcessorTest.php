<?php

namespace Tabula17\Satelles\Odf\Tests;

use PHPUnit\Framework\TestCase;
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\ValidationException;

class OdfProcessorTest extends TestCase
{
    private $tempDir;
    private $tempFile;
    
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/odf_test_' . uniqid();
        mkdir($this->tempDir);
        
        // Crear archivo ODT temporal para pruebas
        $this->tempFile = $this->tempDir . '/test.odt';
        $zip = new \ZipArchive();
        $zip->open($this->tempFile, \ZipArchive::CREATE);
        $zip->addFromString('content.xml', '<?xml version="1.0"?><office:document-content></office:document-content>');
        $zip->close();
    }

    protected function tearDown(): void
    {
        // Limpieza después de las pruebas
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testConstructorWithValidFile()
    {
        $processor = new OdfProcessor(
            $this->tempFile,
            $this->tempDir,
            new OdfContainer(new \ZipArchive())
        );
        
        $this->assertInstanceOf(OdfProcessor::class, $processor);
    }

    public function testConstructorThrowsExceptionWithInvalidFile()
    {
        $this->expectException(FileNotFoundException::class);
        
        new OdfProcessor(
            'archivo_inexistente.odt',
            $this->tempDir,
            new OdfContainer(new \ZipArchive())
        );
    }

    public function testProcessWithEmptyData()
    {
        $processor = new OdfProcessor(
            $this->tempFile,
            $this->tempDir,
            new OdfContainer(new \ZipArchive())
        );

        $this->expectException(ValidationException::class);
        $processor->process([]);
    }
}
