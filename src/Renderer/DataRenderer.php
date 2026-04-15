<?php
declare(strict_types=1);

namespace Tabula17\Satelles\Odf\Renderer;

use Override;
use ReflectionClass;
use ReflectionFunction;
use Tabula17\Satelles\Odf\DataRendererInterface;
use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;
use Tabula17\Satelles\Odf\Functions\Base;
use Tabula17\Satelles\Odf\FunctionsInterface;
use Tabula17\Satelles\Securitas\Evaluator\SafeMathEvaluator;
use Throwable;

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
        get {
            return $this->functions;
        }
        set(FunctionsInterface $functions) {
            $this->functions = $functions;
            $this->argumentsTypes = $this->getMethodArgumentTypes($functions);
        }
    }
    private array $argumentsTypes = [];
    //private string $arithmeticRegEx = self::ARITHMETIC_PATTERN;
    public bool $strictMode;
    public ?array $data;
    private SafeMathEvaluator $safeMathEval;


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
        $this->data = $data;
        $this->functions = $functions ?? new Base();
        $this->strictMode = $strictMode;
        $this->safeMathEval = new SafeMathEvaluator();
    }

    private function getMethodArgumentTypes($objectOrClass): array
    {
        $reflection = new ReflectionClass($objectOrClass);
        $results = [];

        foreach ($reflection->getMethods() as $method) {
            $params = [];
            foreach ($method->getParameters() as $param) {
                // getType() returns ReflectionType (or null if untyped)
                $type = $param->getType();

                // Convert ReflectionType to a readable string (works for PHP 7.1+)
                $params[$param->getName()] = $type ? (string)$type : 'mixed/untyped';
            }
            $results[$method->getName()] = $params;
        }
        return $results;
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
    private function interleaveArrays(array ...$arrays): array
    {
        $total = count($arrays);

        if ($total === 0) {
            return [];
        }

        if ($total === 1) {
            return $arrays[0];
        }

        $result = [];
        $maxLength = max(array_map('count', $arrays));

        for ($i = 0; $i < $maxLength; $i++) {
            foreach ($arrays as $array) {
                if (isset($array[$i])) {
                    $result[] = $array[$i];
                }
            }
        }

        return $result;
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
    #[Override]
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
        return preg_match_all(static::ARITHMETIC_PATTERN, $text, $matches) > 0;
    }

    /**
     * @param array $scriptParts
     * @param array $data
     * @return mixed
     * @throws StrictValueConstraintException
     */
    private function processArithmeticExpression(array $scriptParts, array $data): mixed
    {
        $varMembers = preg_split(static::ARITHMETIC_PATTERN, $scriptParts[0]);
        $this->processMembers($varMembers, $data);

        $arithmetic = [];
        preg_match_all(static::ARITHMETIC_PATTERN, $scriptParts[0], $arithmetic);
        $expression = implode('', (array)$this->interleaveArrays($varMembers, $arithmetic[0]));
        $result = $this->safeMathEval->evaluate($expression);

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

        return array_key_exists($memberPath, $this->data)
            ? $this->data[$memberPath]
            : $default;
    }

    /**
     * Resolves a nested value from an array using a dot-notation path.
     * Handles any level of nesting by traversing the data array according to the path.
     *
     * @param string $path The dot-separated path to the nested value (e.g., "parent.child")
     * @param array $data The data array to traverse
     * @param string|null $default The default value to return if the path doesn't exist
     * @return mixed The resolved value or the default if not found
     */
    private function resolveNestedValue(string $path, array $data, ?string $default): mixed
    {
        $parts = explode('.', $path);

        // Skip the first part if it's empty (which happens with paths like ".child")
        if (empty($parts[0])) {
            array_shift($parts);
        }

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
        array_walk($arguments, function (&$argument, $key, $functionName) {
            if (isset($this->argumentsTypes[$functionName])) {
                $values = array_values($this->argumentsTypes[$functionName]);
            } else {
                try {
                    $reflection = new ReflectionFunction($functionName);
                    $values = [];
                    foreach ($reflection->getParameters() as $parameter) {
                        $values[] = $parameter->getType()?->getName() ?? 'mixed';
                    }
                } catch (Throwable $ignored) {
                    $values = [];
                }
            }


            $argument = $this->castToType($argument, $values[$key] ?? 'mixed');
        }, $functionName);
        return $this->functions->$functionName(...$arguments);
    }

    private function castToType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => is_numeric($value) ? (int)$value : $value,
            'float', 'double' => is_numeric($value) ? (float)$value : $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            'string' => (string)$value,
            default => $value,
        };
    }

    /**
     * @param mixed $value
     * @param string $context
     * @return void
     * @throws StrictValueConstraintException
     */
    private function validateResult(mixed $value, string $context): void
    {
        if ($this->strictMode && $value !== false && empty($value) && $value !== 0 && $value !== '0') {
            throw new StrictValueConstraintException(sprintf(StrictValueConstraintException::EMPTY_VALUE, $context));
        }
    }
}
