<?php

namespace Tabula17\Satelles\Odf\Renderer;

use Tabula17\Satelles\Odf\DataRendererInterface;
use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;
use Tabula17\Satelles\Odf\Functions\Base;
use Tabula17\Satelles\Odf\FunctionsInterface;

/**
 * Class DataRenderer
 *
 * Handles the rendering and processing of structured data with support for arithmetic operations,
 * function application, and strict mode validation.
 */
class DataRenderer implements DataRendererInterface
{
    private const string VALUE_PLACEHOLDER = '__VALUE__';
    private const string ARITHMETIC_PATTERN = '/(?<!")[+\-*\/](?!")/';

    public FunctionsInterface $functions {
        set {
            $this->functions = $value;
        }
        get {
            return $this->functions;
        }
    }
    private string $arithmeticRegEx = self::ARITHMETIC_PATTERN;
    public bool $strictMode {
        set {
            $this->strictMode = $value;
        }
    }
    public ?array $allData {
        set {
            $this->allData = $value;
        }
    }


    /**
     * @param array|null $data
     * @param FunctionsInterface|null $functions
     * @param bool $strictMode
     */
    public function __construct(
        ?array              $data,
        ?FunctionsInterface $functions,
        bool                $strictMode = false
    )
    {
        $this->allData = $data;
        $this->functions = $functions ?? new Base();
        $this->strictMode = $strictMode;
    }

    /**
     * Merges multiple arrays by interleaving their elements sequentially.
     * Each element from the input arrays is placed in a round-robin order
     * into the resulting array. If arrays have different lengths, the
     * remaining elements from longer arrays will be appended in order.
     *
     * @return false|array Returns the interleaved array of elements from the input arrays,
     *                     or false if an error occurs.
     */
    private function interleaveArrays(): false|array
    {
        $args = func_get_args();
        $total = count($args);
        if ($total === 0) {
            return [];
        }
        if ($total === 1) {
            return $args[0];
        }
        $i = 0;
        $j = 0;
        $arr = [];
        foreach ($args as $arg) {
            foreach ($arg as $v) {
                $arr[$j] = $v;
                $j += $total;
            }
            $i++;
            $j = $i;
        }
        ksort($arr);
        return array_values($arr);
    }

    /**
     * Processes a variable by extracting its components, optionally evaluating
     * arithmetic expressions, applying a function, and validating the result.
     *
     * @param string $tag A string containing the variable tag to be processed,
     *                    which may include an arithmetic expression or function.
     * @param array $data An associative array containing the data that the variable
     *                    tag corresponds to, used for resolving its value.
     *
     * @return mixed Returns the processed value after evaluating the tag and applying
     *               any associated function. Defaults to an empty string if no value
     *               is resolved.
     * @throws StrictValueConstraintException
     * @throws StrictValueConstraintException
     */
    public function processVariable(string $tag, array $data): mixed
    {
        $scriptParts = explode('#', $tag, 2);
        $dataMember = $this->extractDataMember($scriptParts[0]);
        if ($this->containsArithmetic($scriptParts[0])) {
            return $this->processArithmeticExpression($scriptParts, $data);
        }

        $value = $this->resolveValue($dataMember[1], $data);

        if (isset($scriptParts[1])) {
            $value = $this->applyFunction($value, $scriptParts[1]);
        }

        $this->validateResult($value, $dataMember[1]);

        return $value ?? '';
    }

    /**
     * @param string $text
     * @return array
     */
    private function extractDataMember(string $text): array
    {
        $matches = [];
        preg_match('/\${(.*)}/', $text, $matches);
        return $matches;
    }

    /**
     * @param string $text
     * @return bool
     */
    private function containsArithmetic(string $text): bool
    {
        return preg_match_all($this->arithmeticRegEx, $text, $matches) > 0;
    }

    /**
     * @param array $scriptParts
     * @param array $data
     * @return mixed
     * @throws StrictValueConstraintException
     */
    private function processArithmeticExpression(array $scriptParts, array $data): mixed
    {
        $varMembers = preg_split($this->arithmeticRegEx, $scriptParts[0]);
        $this->processMembers($varMembers, $data);

        $arithmetic = [];
        preg_match_all($this->arithmeticRegEx, $scriptParts[0], $arithmetic);
        $expression = implode('', (array)$this->interleaveArrays($varMembers, $arithmetic[0]));
        $result = eval('return ' . $expression . ';');

        $this->validateResult($result, $expression);

        if (isset($scriptParts[1])) {
            $result = $this->applyFunction($result, $scriptParts[1]);
        }

        return $result ?? '';
    }

    /**
     * @param array $members
     * @param array $data
     * @return void
     * @throws StrictValueConstraintException
     */
    private function processMembers(array &$members, array $data): void
    {
        array_walk($members, function (&$member) use ($data) {
            $member = $this->processVariable($member, $data);
            $member = !$member ? '0' : $member;
        });
    }

    /**
     * @param string $path
     * @param array $data
     * @return mixed
     */
    private function resolveValue(string $path, array $data): mixed
    {
        $parts = explode('?', $path);
        $memberPath = $parts[0];
        $default = $parts[1] ?? null;
        if (str_contains($memberPath, '.')) {
            return $this->resolveNestedValue($memberPath, $data, $default);
        }

        return array_key_exists($memberPath, $this->allData)
            ? $this->allData[$memberPath]
            : $default;
    }

    /**
     * @param string $path
     * @param array $data
     * @param string|null $default
     * @return mixed
     */
    private function resolveNestedValue(string $path, array $data, ?string $default): mixed
    {
        $parts = explode('.', $path);
        $key = $parts[1];
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    /**
     * @param mixed $value
     * @param string $functionString
     * @return mixed
     */
    private function applyFunction(mixed $value, string $functionString): mixed
    {
        $arguments = explode('|', $functionString);
        $functionName = array_shift($arguments);

        $valuePosition = array_search(self::VALUE_PLACEHOLDER, $arguments);
        if ($valuePosition !== false) {
            $arguments[$valuePosition] = $value;
        } else {
            array_unshift($arguments, $value);
        }

        return $this->functions->$functionName(...$arguments);
    }

    /**
     * @param mixed $value
     * @param string $context
     * @return void
     * @throws StrictValueConstraintException
     */
    private function validateResult(mixed $value, string $context): void
    {
        if (($value === false || $value === null) && $this->strictMode) {
            throw new StrictValueConstraintException(
                "Strict mode enabled. Value is not valid: $context"
            );
        }
    }
}