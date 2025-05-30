<?php

namespace Tabula17\Satelles\Odf\Template;

use Tabula17\Satelles\Odf\DataRendererInterface;
use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;
use Tabula17\Satelles\Odf\OdfContainerInterface;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;
use Tabula17\Satelles\Odf\XmlProcessorInterface;
use Tabula17\Satelles\Xml\XmlPart;


/**
 * The TemplateProcessor class provides functionality for processing XML templates.
 * It enables handling of various template constructs such as dynamic loops,
 * conditional logic, and media rendering within XML documents.
 */
class XmlProcessor implements XmlProcessorInterface
{
    private const string TEMPLATE_PREFIX = 'odf-tpl-';
    private const array XPATH_REPLACEMENTS = [
        '/up@/' => 'ancestor-or-self::',
        '/down@/' => 'descendant-or-self::',
        '/left@/' => 'parent::*/preceding-sibling::',
        '/right@/' => 'parent::*/following-sibling::'
    ];
    public DataRendererInterface $renderer;
    public OdfContainerInterface $fileContainer;

    /**
     * Constructor method to initialize the object with required dependencies.
     *
     * @param DataRenderer $renderer Instance of the data renderer.
     * @param OdfContainerInterface $fileContainer Instance of the file container.
     * @return void
     */
    public function __construct(DataRendererInterface $renderer, OdfContainerInterface $fileContainer)
    {
        $this->renderer = $renderer;
        $this->fileContainer = $fileContainer;
    }

    /**
     * Retrieves the template name based on the given type.
     *
     * @param TemplateConfig $type The type identifier used to fetch the corresponding template.
     * @return string Returns the name of the template.
     */
    public function getTemplateName(TemplateConfig $type): string
    {
        return $type->label(self::TEMPLATE_PREFIX);
    }

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
    public function processTemplate(XmlPart $xml, array $data, ?string $alias = null): void
    {
        if ($alias === null) {
            $this->renderer->allData = $data;
            $this->processIfTemplates($xml, $data);
            $this->processLoopTemplates($xml, $data);
            $this->processTextNodes($xml, $data);
            $this->processMedia($xml, $data, $this->getTemplateName(TemplateConfig::IMAGE));
            $this->processMedia($xml, $data, $this->getTemplateName(TemplateConfig::SVG));
        } else {
            $this->processTemplatesInLoop($xml, $data, $alias);
        }
    }

    /**
     * Processes templates in a loop, duplicating and populating the XML template
     * for each set of data values. Handles conditional templates, nested loops,
     * text nodes, and media replacements specific to the provided alias.
     *
     * @param XmlPart $xml The XML template to duplicate and populate for each data set.
     * @param array $data An associative array of data sets to apply to the template.
     * @param string $alias The alias identifying the specific section or context for processing.
     * @return void
     * @throws StrictValueConstraintException
     */
    private function processTemplatesInLoop(XmlPart $xml, array $data, string $alias): void
    {
        foreach ($data as $values) {
            $loopNode = $xml->duplicate();
            $this->processIfTemplates($loopNode, $values);
            $this->processLoopTemplates($loopNode, $values, $alias);
            $this->processTextNodes($loopNode, $values, $alias);
            $this->processMedia($loopNode, $values, $this->getTemplateName(TemplateConfig::IMAGE_LOOP));
            $this->processMedia($loopNode, $values, $this->getTemplateName(TemplateConfig::SVG_LOOP));
        }
    }

    /**
     * Processes conditional templates in the given XML by evaluating "if" conditions
     * and updating the XML structure based on the provided data.
     *
     * @param XmlPart $xml The XML object containing the templates to process.
     * @param array $data An associative array of data used to evaluate conditions in the templates.
     * @return void
     */
    private function processIfTemplates(XmlPart $xml, array $data): void
    {
        $ifTplSearch = ".//text:text-input[@text:description=\"{$this->getTemplateName(TemplateConfig::IF)}\"][not(ancestor::text:text-input[@text:description=\"{$this->getTemplateName(TemplateConfig::LOOP)}\"])]";
        $ifNodes = $xml->xpath($ifTplSearch);
        /**
         * @var XmlPart $node
         */
        foreach ($ifNodes as $node) {
            $scriptParts = explode('#', $node, 2);
            if (count($scriptParts) === 2) {
                $ifNodeQuery = preg_replace(array_keys(self::XPATH_REPLACEMENTS), array_values(self::XPATH_REPLACEMENTS), $scriptParts[1]);
                $ifNode = $node->xpath($ifNodeQuery);
                if ($ifNode) {
                    $ifNode = $ifNode[0];
                    if (!$this->evaluateExpression($scriptParts[0], $data)) {
                        $ifNode->delete();
                    }
                }
            }
            $node->delete();
        }
    }

    /**
     * Processes loop templates within the provided XML by identifying and
     * replacing loop placeholders using the supplied data. If an alias is specified,
     * it targets a specific portion of the XML.
     *
     * @param XmlPart $xml The XML representation of the template to process.
     * @param array $data An associative array containing the data for loop substitution.
     * @param string|null $alias An optional alias to limit processing to a specific section of the template.
     * @return void
     */
    private function processLoopTemplates(XmlPart $xml, array $data, ?string $alias = null): void
    {
        $loopTplSearch = "descendant::text:text-input[@text:description=\"{$this->getTemplateName(TemplateConfig::LOOP)}\"]";
        $loopNodes = $xml->xpath($loopTplSearch);
        foreach ($loopNodes as $node) {
            $this->processLoopNode($node, $data);
        }
    }

    /**
     * Processes a loop node within the XML structure by evaluating the provided data
     * and dynamically applying transformations. Repeats the processing on matching nodes
     * and substitutes content based on the specified loop configuration.
     *
     * @param mixed $node The loop node to process, which may include query and alias definitions.
     * @param array $data An associative array containing the data used for populating the loop.
     * @return void
     * @throws StrictValueConstraintException
     */
    private function processLoopNode(mixed $node, array $data): void
    {
        $scriptParts = explode('#', $node, 2);
        if (count($scriptParts) === 2) {
            $loopNodeQuery = preg_replace(array_keys(self::XPATH_REPLACEMENTS), array_values(self::XPATH_REPLACEMENTS), $scriptParts[0]);
            $loopDataMembers = explode('as', strtolower($scriptParts[1]));
            $loopMember = trim($loopDataMembers[0]);
            $loopAlias = trim($loopDataMembers[1]);
            if (isset($data[$loopMember]) && is_array($data[$loopMember])) {
                $loopNode = $node->xpath($loopNodeQuery);
                $node->setAttribute('text:description', "{$this->getTemplateName(TemplateConfig::LOOP)}-$loopMember"); //
                $node->delete();
                foreach ($loopNode as $loop) {
                    $this->processTemplate($loop, $data[$loopMember], $loopAlias);
                    $loop->delete();
                }
            }
        }
    }

    /**
     * Processes text nodes in the provided XML, replacing placeholders with corresponding values from the data array.
     * If an alias is provided, only text nodes matching the alias will be processed.
     *
     * @param XmlPart $xml The XML part containing the text nodes to process.
     * @param array $data An associative array of data to replace placeholders within the text nodes.
     * @param string|null $alias An optional alias to filter text nodes for processing.
     * @return void
     * @throws StrictValueConstraintException
     */
    private function processTextNodes(XmlPart $xml, array $data, ?string $alias = null): void
    {
        $textNodes = $xml->xpath("descendant::text:text-input[@text:description=\"{$this->getTemplateName(TemplateConfig::TEXT)}\"]");
        foreach ($textNodes as $textNode) {
            if (preg_match('/\${(.*)}/', $textNode) === 1) {
                if ($alias !== null && !str_contains($textNode, $alias . '.')) {
                    continue;
                }
                $value = $this->renderer->processVariable((string)$textNode, $data);
                $textNode->replaceWithText($value);
            }
        }
    }

    /**
     * Processes media nodes within the given XML structure and updates them based on the provided values.
     * Specifically handles SVG or image nodes by applying changes according to a search template.
     *
     * @param XmlPart $node The XML structure containing media nodes to be processed.
     * @param array $values An associative array of values used to update the media nodes.
     * @param string $searchTpl The search template defining the target media nodes to process.
     * @return void
     * @throws StrictValueConstraintException
     */
    private function processMedia(XmlPart $node, array $values, string $searchTpl): void
    {

        $isSvg = str_contains($searchTpl, 'svg');
        $attr = $isSvg ? 'svg:title' : '@draw:name';
        $searchPattern = "descendant::draw:frame[$attr=\"$searchTpl\"]";
        $svgNodes = $node->xpath($searchPattern);
        $variableSelector = $isSvg ? 'svg:desc' : 'svg:title';
        $imageSelector = $isSvg ? 'draw:image[@draw:mime-type="image/svg+xml"]' : 'draw:image';
        $images = [];
        $imageNames = [];
        foreach ($svgNodes as $drawNode) {
            $this->processMediaNode($drawNode, $variableSelector, $imageSelector, $values, $images, $imageNames);
        }
        $this->fileContainer->addImages($images, $imageNames);
    }

    /**
     * Processes a media node within the provided XML structure, replacing variable placeholders
     * with corresponding values and linking image nodes to their respective files.
     *
     * @param XmlPart $drawNode The XML node representing the media element to be processed.
     * @param string $variableSelector An XPath selector to locate the placeholder variable within the node.
     * @param string $imageSelector An XPath selector to locate the image node within the media element.
     * @param array $values An associative array of data used to replace variable placeholders.
     * @param array &$images A reference to an array that will store the file paths of processed images.
     * @param array &$imageNames A reference to an array that will store the names of processed image files.
     * @return void
     * @throws StrictValueConstraintException
     */
    private function processMediaNode(XmlPart $drawNode, string $variableSelector, string $imageSelector, array $values, array &$images, array &$imageNames): void
    {
        $variable = $drawNode->xpath($variableSelector);
        if ($variable) {
            $variable = $variable[0];
            $value = $this->renderer->processVariable((string)$variable, $values);
            $imgNode = $drawNode->xpath($imageSelector);
            if ($imgNode) {
                $imgNode = $imgNode[0];
            }
            if (!file_exists($value)) {
                return;
            }
            $fileName = basename($value);
            $mime = mime_content_type($value);
            $this->fileContainer->registerFileInManifest($fileName, $mime);

            $imgNode->setAttribute('xlink:href', $this->fileContainer->getPicturesFolder() . $fileName);
            $images[] = $value;
            $imageNames[] = $fileName;
        }
    }

    /**
     * Evaluates a given expression using dynamic data and determines its boolean result.
     * The method processes placeholders in the expression by replacing them with data values
     * and evaluates the resulting logical expression.
     *
     * @param string $expression The expression to be evaluated, which may include placeholders in the form ${variable}.
     * @param array $data An associative array containing data to replace placeholders in the expression.
     * @return bool The result of the evaluated expression as a boolean value.
     * @throws StrictValueConstraintException
     */
    private function evaluateExpression(string $expression, array $data): bool
    {
        preg_match_all('/\${.*?}|.(?![^{]*})/', $expression, $calc);
        $doValue = function ($value) use ($data) {
            if (is_string($value) && preg_match('/\${(.*)}/', $value) === 1) {
                $value = $this->renderer->processVariable($value, $data);
                if (!is_numeric($value)) {
                    $value = "'$value'";
                }
            }
            return $value;
        };
        $calc = array_map($doValue, $calc[0]);

        // Handle special case for 'in' and 'notin' operators
        if (count($calc) === 3 && (strtolower($calc[1]) === 'in' || strtolower($calc[1]) === 'notin')) {
            $haystack = explode(',', $calc[2]);
            return strtolower($calc[1]) === 'notin' ? !in_array($doValue($calc[0]), $haystack) : in_array($doValue($calc[0]), $haystack);
        }

        // Safe expression evaluation without eval()
        return $this->safeEvaluateExpression($calc);
    }

    /**
     * Safely evaluates an expression without using eval().
     * Supports basic comparison and logical operators.
     *
     * @param array $tokens Array of tokens representing the expression
     * @return bool The result of the evaluated expression
     */
    private function safeEvaluateExpression(array $tokens): bool
    {
        // Remove any empty tokens
        $tokens = array_values(array_filter($tokens, function($token) {
            return $token !== '' && $token !== ' ';
        }));

        // Simple expression with just one token (e.g., a boolean value)
        if (count($tokens) === 1) {
            $value = $tokens[0];
            if (is_bool($value)) {
                return $value;
            }
            if (is_string($value)) {
                $lowerValue = strtolower(trim($value, "'\""));
                if ($lowerValue === 'true') return true;
                if ($lowerValue === 'false') return false;
                if ($lowerValue === '1') return true;
                if ($lowerValue === '0') return false;
                if ($lowerValue === '') return false;
                return (bool)$value;
            }
            return (bool)$value;
        }

        // Handle comparison operators
        if (count($tokens) === 3) {
            $left = $this->normalizeValue($tokens[0]);
            $operator = trim($tokens[1]);
            $right = $this->normalizeValue($tokens[2]);

            switch ($operator) {
                case '==':
                case '=':
                    return $left == $right;
                case '!=':
                case '<>':
                    return $left != $right;
                case '>':
                    return $left > $right;
                case '<':
                    return $left < $right;
                case '>=':
                    return $left >= $right;
                case '<=':
                    return $left <= $right;
                case '&&':
                case 'and':
                    return $left && $right;
                case '||':
                case 'or':
                    return $left || $right;
            }
        }

        // Handle more complex expressions with logical operators
        if (count($tokens) > 3) {
            // Look for 'and' or '&&' operators
            $andPos = array_search('and', array_map('strtolower', $tokens));
            if ($andPos === false) {
                $andPos = array_search('&&', $tokens);
            }

            if ($andPos !== false) {
                $leftExpr = array_slice($tokens, 0, $andPos);
                $rightExpr = array_slice($tokens, $andPos + 1);
                return $this->safeEvaluateExpression($leftExpr) && $this->safeEvaluateExpression($rightExpr);
            }

            // Look for 'or' or '||' operators
            $orPos = array_search('or', array_map('strtolower', $tokens));
            if ($orPos === false) {
                $orPos = array_search('||', $tokens);
            }

            if ($orPos !== false) {
                $leftExpr = array_slice($tokens, 0, $orPos);
                $rightExpr = array_slice($tokens, $orPos + 1);
                return $this->safeEvaluateExpression($leftExpr) || $this->safeEvaluateExpression($rightExpr);
            }
        }

        // Default fallback - if we can't evaluate, return false
        return false;
    }

    /**
     * Normalizes a value for comparison by removing quotes and converting to appropriate type.
     *
     * @param mixed $value The value to normalize
     * @return mixed The normalized value
     */
    private function normalizeValue($value)
    {
        if (is_numeric($value)) {
            return $value + 0; // Convert to int or float
        }

        if (is_string($value)) {
            // Remove surrounding quotes if present
            $value = trim($value);
            if ((substr($value, 0, 1) === "'" && substr($value, -1) === "'") || 
                (substr($value, 0, 1) === '"' && substr($value, -1) === '"')) {
                $value = substr($value, 1, -1);
            }

            // Convert string boolean values
            $lowerValue = strtolower($value);
            if ($lowerValue === 'true') return true;
            if ($lowerValue === 'false') return false;
        }

        return $value;
    }
}
