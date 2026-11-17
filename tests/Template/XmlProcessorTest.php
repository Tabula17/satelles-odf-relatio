<?php

namespace Tabula17\Satelles\Odf\Tests\Template;

use PHPUnit\Framework\TestCase;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;
use Tabula17\Satelles\Odf\Template\XmlProcessor;
use Tabula17\Satelles\Xml\XmlPart;
use Tabula17\Satelles\Odf\Functions\Base;

class XmlProcessorTest extends TestCase
{
    private const string XML_HEADER = '<?xml version="1.0"?>
        <office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" xmlns:xlink="http://www.w3.org/1999/xlink">';

    private XmlProcessor $processor;
    private DataRenderer $renderer;
    private OdfContainer $container;

    protected function setUp(): void
    {
        $this->renderer = new DataRenderer([], new Base());
        $this->container = $this->createMock(OdfContainer::class);
        $this->container->method('getPicturesFolder')->willReturn('Pictures/');
        $this->processor = new XmlProcessor($this->renderer, $this->container);
    }

    public function testProcessTemplateWithSimpleText(): void
    {
        $xml = new XmlPart(self::XML_HEADER . '
                <text:text-input text:description="odf-tpl-text">${name}</text:text-input>
            </office:document-content>');

        $this->processor->processTemplate($xml, ['name' => 'John Doe']);
        
        $result = $xml->asXml();
        $this->assertStringContainsString('John Doe', $result);
        $this->assertStringNotContainsString('${name}', $result);
    }

    public function testProcessTemplateWithLoop(): void
    {
        $xml = new XmlPart(self::XML_HEADER . '
                <table:table>
                    <table:table-row>
                        <text:text-input text:description="odf-tpl-loop">up@table:table-row#items as list</text:text-input>
                        <table:table-cell>
                            <text:p>
                                <text:text-input text:description="odf-tpl-text">${list.name}</text:text-input>
                            </text:p>
                        </table:table-cell>
                    </table:table-row>
                </table:table>
            </office:document-content>');

        $data = ['items' => [
            ['name' => 'Item 1'],
            ['name' => 'Item 2']
        ]];

        $this->processor->processTemplate($xml, $data);
        
        $result = $xml->asXml();
        $this->assertStringContainsString('Item 1', $result);
        $this->assertStringContainsString('Item 2', $result);
    }

    public function testProcessTemplateWithConditional(): void
    {
        $xml = new XmlPart(self::XML_HEADER . '
                <text:text-input text:description="odf-tpl-if">${show}==true#down@text:p</text:text-input>
                <text:p>Visible content</text:p>
            </office:document-content>');

        $this->processor->processTemplate($xml, ['show' => true]);
        
        $result = $xml->asXml();
        $this->assertStringContainsString('Visible content', $result);
    }

    public function testProcessTemplateWithNestedConditions(): void
    {
        $xml = new XmlPart(self::XML_HEADER . '
                <text:text-input text:description="odf-tpl-if">${outer}==true#down@text:p</text:text-input>
                <text:p>
                    <text:text-input text:description="odf-tpl-if">${inner}==true#down@text:span</text:text-input>
                    <text:span>Nested content</text:span>
                </text:p>
            </office:document-content>');

        $this->processor->processTemplate($xml, [
            'outer' => true,
            'inner' => true
        ]);
        
        $result = $xml->asXml();
        $this->assertStringContainsString('Nested content', $result);
    }

    public function testProcessTemplateWithMedia(): void
    {
        $xml = new XmlPart(self::XML_HEADER . '
                <draw:frame draw:name="odf-tpl-image">
                    <svg:title>${image}</svg:title>
                    <draw:image xlink:href=""/>
                </draw:frame>
            </office:document-content>');

        // Create a temporary test image
        $tempImage = tempnam(sys_get_temp_dir(), 'test_img_');
        file_put_contents($tempImage, 'fake image content');

        $this->container->expects($this->once())
            ->method('registerFileInManifest')
            ->with($this->anything(), $this->anything());

        $this->processor->processTemplate($xml, ['image' => $tempImage]);

        $result = $xml->asXml();
        $this->assertStringContainsString('Pictures/', $result);
        
        unlink($tempImage);
    }

    public function testProcessTemplateWithSvgMediaWithoutMimeType(): void
    {
        $xml = new XmlPart(self::XML_HEADER . '
                <draw:frame draw:name="Forma1">
                    <draw:image xlink:href="Pictures/preview.png"/>
                    <draw:image xlink:href="Pictures/template.svg"/>
                    <svg:title>odf-tpl-svg</svg:title>
                    <svg:desc>${svgImage}</svg:desc>
                </draw:frame>
            </office:document-content>');

        $tempSvg = tempnam(sys_get_temp_dir(), 'test_svg_') . '.svg';
        file_put_contents($tempSvg, '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');

        $this->container->expects($this->once())
            ->method('registerFileInManifest')
            ->with(basename($tempSvg), $this->anything());

        $this->processor->processTemplate($xml, ['svgImage' => $tempSvg]);

        $result = $xml->asXml();
        $this->assertStringContainsString('Pictures/' . basename($tempSvg), $result);
        $this->assertStringNotContainsString('Pictures/preview.png', $result);
        $this->assertStringContainsString('draw:mime-type="image/svg+xml"', $result);

        unlink($tempSvg);
    }

    public function testProcessTemplateWithSvgLoopMedia(): void
    {
        $xml = new XmlPart(self::XML_HEADER . '
                <table:table>
                    <table:table-row>
                        <text:text-input text:description="odf-tpl-loop">up@table:table-row#items as item</text:text-input>
                        <table:table-cell>
                            <draw:frame draw:name="Forma1">
                                <draw:image xlink:href="Pictures/preview.png"/>
                                <draw:image xlink:href="Pictures/template.svg"/>
                                <svg:title>odf-tpl-svg-loop</svg:title>
                                <svg:desc>${item.svg}</svg:desc>
                            </draw:frame>
                        </table:table-cell>
                    </table:table-row>
                </table:table>
            </office:document-content>');

        $tempSvg = tempnam(sys_get_temp_dir(), 'test_svg_') . '.svg';
        file_put_contents($tempSvg, '<svg xmlns="http://www.w3.org/2000/svg"><circle/></svg>');

        $this->container->expects($this->once())
            ->method('registerFileInManifest')
            ->with(basename($tempSvg), $this->anything());

        $this->processor->processTemplate($xml, ['items' => [['svg' => $tempSvg]]]);

        $result = $xml->asXml();
        $this->assertStringContainsString('Pictures/' . basename($tempSvg), $result);
        $this->assertStringNotContainsString('Pictures/preview.png', $result);
        $this->assertStringContainsString('draw:mime-type="image/svg+xml"', $result);

        unlink($tempSvg);
    }
}
