<?php

namespace Tabula17\Satelles\Odf\Tests\Renderer;

use PHPUnit\Framework\TestCase;
use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;
use Tabula17\Satelles\Odf\Functions\Base;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;

class DataRendererTest extends TestCase
{
    private DataRenderer $renderer;
    
    protected function setUp(): void
    {
        $this->renderer = new DataRenderer([], new Base());
    }

    public function testProcessVariableWithSimpleValue(): void
    {
        $data = ['name' => 'John Doe'];
        $this->renderer->allData = $data;
        
        $result = $this->renderer->processVariable('${name}', $data);
        
        $this->assertEquals('John Doe', $result);
    }

    public function testProcessVariableWithNestedValue(): void
    {
        $data = ['user' => ['name' => 'John Doe']];
        
        $result = $this->renderer->processVariable('${user.name}', $data);
        
        $this->assertEquals('John Doe', $result);
    }

    public function testProcessVariableWithArithmeticOperation(): void
    {
        $data = ['price' => 10, 'tax' => 2];
        $this->renderer->allData = $data;
        
        $result = $this->renderer->processVariable('${price}+${tax}', $data);
        
        $this->assertEquals(12, $result);
    }

    public function testProcessVariableWithFunction(): void
    {
        $data = ['text' => 'hello'];
        $this->renderer->allData = $data;
        
        $result = $this->renderer->processVariable('${text}#upper', $data);
        
        $this->assertEquals('HELLO', $result);
    }

    public function testProcessVariableWithDefaultValue(): void
    {
        $result = $this->renderer->processVariable('${missing?default}', []);
        
        $this->assertEquals('default', $result);
    }

    public function testStrictModeThrowsExceptionOnEmptyValue(): void
    {
        $this->renderer->strictMode = true;
        
        $this->expectException(StrictValueConstraintException::class);
        $this->renderer->processVariable('${nonexistent}', []);
    }

    public function testMultipleArithmeticOperations(): void
    {
        $data = ['a' => 10, 'b' => 5, 'c' => 2];
        $this->renderer->allData = $data;
        
        $result = $this->renderer->processVariable('${a}+${b}*${c}', $data);
        
        $this->assertEquals(20, $result);
    }

    public function testFunctionWithMultipleArguments(): void
    {
        $data = ['text' => 'hello world'];
        $this->renderer->allData = $data;
        
        $result = $this->renderer->processVariable('${text}#substr|0|5', $data);
        
        $this->assertEquals('hello', $result);
    }
}
