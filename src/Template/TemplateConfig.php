<?php

namespace Tabula17\Satelles\Odf\Template;

enum TemplateConfig: string
{
    /**
     * 'text' => 'text',
     * 'loop' => 'loop',
     * 'image' => 'image',
     * 'imageLoop' => 'image-loop',
     * 'svg' => 'svg',
     * 'svgLoop' => 'svg-loop',
     * 'if' => 'if',
     * 'else' => 'else',
     * 'endif' => 'endif'
     */
    case TEXT = 'text';
    case LOOP = 'loop';
    case IMAGE = 'image';
    case IMAGE_LOOP = 'image-loop';
    case SVG = 'svg';
    case SVG_LOOP = 'svg-loop';
    case IF = 'if';
    case ELSE = 'else';
    case ENDIF = 'endif';
    //case PREFIX = 'prefix';
    public function label(string $prefix): string
    {
        return $prefix . $this->value;
    }
}
