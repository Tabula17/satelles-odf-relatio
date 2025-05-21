<?php

namespace Tabula17\Satelles\Odf\Template;
/**
 * Enumeration representing various template configuration options.
 *
 * This enum provides a set of string values for defining specific template types
 * or conditions used in a templating system. Each case maps to a specific string
 * value for easy reference.
 */
enum TemplateConfig: string
{
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

    /**
     * Generates a formatted label by concatenating the provided prefix with the value property.
     *
     * @param string $prefix The prefix to prepend to the value.
     * @return string The resulting formatted label.
     */
    public function label(string $prefix): string
    {
        return $prefix . $this->value;
    }
}
