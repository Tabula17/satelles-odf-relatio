<?php

namespace Tabula17\Satelles\Odf;
use InvalidArgumentException;

/**
 * Class TemplateConfig
 *
 * A configuration class for managing and retrieving template names based on types.
 */
class TemplateConfig
{

    public string $prefix;
    private array $templates = [
        'text' => 'text',
        'loop' => 'loop',
        'image' => 'image',
        'imageLoop' => 'image-loop',
        'svg' => 'svg',
        'svgLoop' => 'svg-loop',
        'if' => 'if',
        'else' => 'else',
        'endif' => 'endif'
    ];

    /**
     * @param string $prefix
     */
    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Retrieves the name of the template based on the provided type.
     *
     * @param string $type The type of template to retrieve.
     * @return string The fully qualified name of the template.
     * @throws InvalidArgumentException If the provided type does not exist in the template list.
     */
    public function getTemplateName(string $type): string
    {
        if (!isset($this->templates[$type])) {
            throw new InvalidArgumentException("Tipo de plantilla no válido: $type");
        }
        return $this->prefix . $this->templates[$type];
    }
}