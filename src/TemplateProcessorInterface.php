<?php

namespace Tabula17\Satelles\Odf;


use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;
use Tabula17\Satelles\Odf\Template\TemplateConfig;
use Tabula17\Satelles\Xml\XmlPart;

/**
 * The XmlProcessor class provides functionality for processing XML templates.
 * It enables handling of various template constructs such as dynamic loops,
 * conditional logic, and media rendering within XML documents.
 */
interface TemplateProcessorInterface
{
    /**
     * Retrieves the template name based on the given type.
     *
     * @param TemplateConfig $type The type identifier used to fetch the corresponding template TAG.
     * @return string Returns the name of the template TAG.
     */
    public function getTemplateName(TemplateConfig $type): string;

    /**
     * Processes the given XML template by replacing placeholders with provided data.
     * Depending on whether an alias is specified, operates on the entire template
     * or a specific subset of it.
     *
     * @param XmlPart $xml The XML template to process.
     * @param array $data An associative array containing data to populate the template.
     * @param string|null $alias An optional alias to target a specific section of the template.
     * @return void
     * @throws StrictValueConstraintException
     */
    public function processTemplate(XmlPart $xml, array $data, ?string $alias = null): void;

}