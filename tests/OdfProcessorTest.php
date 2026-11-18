<?php

namespace Tabula17\Satelles\Odf\Tests;

use PHPUnit\Framework\TestCase;
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\ValidationException;
use Tabula17\Satelles\Odf\DataRendererInterface;
use Tabula17\Satelles\Odf\FunctionsInterface;
use Tabula17\Satelles\Odf\Functions\Base;
use Tabula17\Satelles\Odf\OdfContainerInterface;
use Tabula17\Satelles\Odf\Template\TemplateConfig;
use Tabula17\Satelles\Odf\XmlProcessorInterface;
use Tabula17\Satelles\Xml\XmlPart;

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
        $xml = '<?xml version="1.0"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"/>';
        $zip->addFromString('content.xml', $xml);
        $zip->addFromString('styles.xml', $xml);
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

    public function testInjectedDependenciesAreSharedAndUsedForProcessing(): void
    {
        foreach ([false, true] as $explicitRenderer) {
            foreach ([false, true] as $explicitContainer) {
                $originalRenderer = $this->newRenderer();
                $originalContainer = new OdfContainer(new \ZipArchive());
                $renderer = $explicitRenderer ? $this->newRenderer() : $originalRenderer;
                $container = $explicitContainer ? $this->createMock(OdfContainerInterface::class) : $originalContainer;
                if ($explicitContainer) {
                    $container->expects($this->once())->method('loadFile');
                    $container->method('getPart')->willReturn(new XmlPart('<root/>'));
                }
                $xml = new class($originalRenderer, $originalContainer) implements XmlProcessorInterface {
                    public array $calls = [];

                    public function __construct(public DataRendererInterface $renderer, public OdfContainerInterface $fileContainer) {}
                    public function getTemplateName(TemplateConfig $type): string { return ''; }
                    public function processTemplate(XmlPart $xml, array $data, ?string $alias = null): void
                    {
                        $this->calls[] = [$this->renderer->data, $data, $alias];
                    }
                };

                $processor = new OdfProcessor(
                    $this->tempFile,
                    $this->tempDir,
                    $explicitContainer ? $container : null,
                    $explicitRenderer ? $renderer : null,
                    $xml
                );

                $this->assertSame($renderer, $xml->renderer);
                $this->assertSame($container, $xml->fileContainer);
                $this->assertSame($processor->workingDir, $renderer->functions->workingDir);
                $data = [['name' => 'Jane']];
                $processor->process($data, 'items');
                $this->assertSame([[$data, $data, 'items'], [$data, $data, 'items']], $xml->calls);
                $processor->process(['name' => 'John']);
                $this->assertSame([['name' => 'John'], ['name' => 'John'], null], $xml->calls[2]);
                $processor->reset();
                $this->assertNull($renderer->data);
                unset($processor);
            }
        }
    }

    public function testCustomRendererWorksWithDefaultXmlProcessor(): void
    {
        $renderer = $this->newRenderer();
        $processor = new OdfProcessor($this->tempFile, $this->tempDir, renderer: $renderer);

        $this->assertSame($processor->workingDir, $renderer->functions->workingDir);
        $processor->process(['name' => 'Jane']);
        $this->assertSame(['name' => 'Jane'], $renderer->data);
        $processor->reset();
        $this->assertNull($renderer->data);
    }

    private function newRenderer(): DataRendererInterface
    {
        return new class implements DataRendererInterface {
            public FunctionsInterface $functions;
            public bool $strictMode = false;
            public ?array $data = null;

            public function __construct() { $this->functions = new Base(); }
            public function processVariable(string $tag, array $data): mixed { return null; }
        };
    }
}
