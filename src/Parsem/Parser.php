<?php

declare(strict_types=1);

namespace Matronator\Parsem;

use Exception;
use InvalidArgumentException;
use Matronator\Parsem\Config\PatternsOption;
use Nette\Neon\Neon;
use Opis\JsonSchema\Validator;
use RuntimeException;
use SplFileObject;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Parser
 * @package Matronator\Parsem
 * @author Matronator <info@matronator.cz>
 */
final class Parser
{
    /**
     * Matches:
     * 0: <% var='default'|filter:10,'arg','another' %> --> (full match)
     * 1: var                                           --> (only variable name)
     * 2: ='default'                                    --> (default value)
     * 3: |filter:10,'arg','another'                    --> (filter with args)
     * 4: filter                                        --> (only filter name)
     */
    public const VARIABLE_PATTERN = '/<%\s?((?!endif|else)[a-zA-Z0-9_]+)(=.*?)?(\|([a-zA-Z0-9_]+?)(?:\:(?:(?:\\?\'|\\?")?.?(?:\\?\'|\\?")?,?)+?)*?)?\s?%>/m';

    /**
     * Matches:
     * 0: <% if a > 10 %> --> (full match)
     * 1: a > 10          --> (only condition)
     * 2: !               --> (negation)
     * 3: a               --> (left side)
     * 4: > 10            --> (right side with operator)
     * 5: >               --> (operator)
     * 6: 10              --> (right side)
     */
    public const CONDITION_PATTERN = '/(?<all><%\s?if\s(?<condition>(?<negation>!?)(?<left>\S+?)\s?(?<right>(?<operator>(?:<=|<|===|==|>=|>|!==|!=))\s?(?<value>.+?))?)\s?%>\n?)/m';

    /**
     * Matches:
     * 0: <# comment #> --> (full match)
     * 1: comment       --> (only comment)
     */
    public const COMMENT_PATTERN = '/<#\s?(.*?)\s?#>/ms';

    /** @internal */
    private const LITERALLY_NULL = '⚠︎__:-␀LITERALLY_NULL␀-:__⚠︎';

    /**
     * Parses a file to a PHP object, replacing all template variables with the provided `$arguments` values.
     * @since 3.2.0
     * @return string The parsed string
     * @param string $filename File to parse
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param bool $strict [optional] If set to `true, the function will throw an exception if a variable is not found in the `$arguments` array. If set to `false` null will be used.
     * @param array $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     * @throws RuntimeException If the file does not exist
     */
    public static function parse(string $filename, array $arguments = [], bool $strict = true, PatternsOption $patterns = new PatternsOption()): string
    {
        if (!file_exists($filename))
            throw new RuntimeException("File '$filename' does not exist.");

        $contents = file_get_contents($filename);
        return static::parseString($contents, $arguments, $strict, $patterns);
    }

    /**
     * Parses a string, replacing all template variables with the corresponding values passed in `$arguments`.
     * @return mixed The parsed string or the original `$string` value if it's not string
     * @param mixed $string String to parse. If not provided with a string, the function will return this value
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param bool $strict [optional] If set to `true`, the function will throw an exception if a variable is not found in the `$arguments` array. If set to `false` null will be used.
     * @param array $patterns [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     * @throws RuntimeException If a variable is not found in the `$arguments` array and `$strict` is set to `true`
     */
    public static function parseString(mixed $string, array $arguments = [], bool $strict = true, PatternsOption $patterns = new PatternsOption()): mixed
    {
        if (!is_string($string)) return $string;

        $string = static::removeComments($string, $patterns['comments'] ?? null);
        $string = static::parseConditions($string, $arguments, pattern: $patterns['conditions'] ?? null);

        preg_match_all($patterns['variables'] ?? static::VARIABLE_PATTERN, $string, $matches);
        $args = [];
        foreach ($matches[1] as $key => $match) {
            if (isset($arguments[$match])) {
                $args[] = $arguments[$match];
            } else {
                $default = static::getDefaultValue($matches[2][$key]);
                if ($default !== static::LITERALLY_NULL) {
                    $args[] = $default;
                } else {
                    if ($strict) {
                        throw new RuntimeException("Variable '$match' not found in arguments.");
                    }

                    $args[] = null;
                }
            }
        }

        $args = static::applyFilters($matches, $args);
        foreach ($args as $key => $arg) {
            if (is_array($arg)) {
                $args[$key] = json_encode($arg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else if (is_object($arg)) {
                $args[$key] = json_encode($arg, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return str_replace($matches[0], $args, $string);
    }

    /**
     * Parses a string, replacing all conditional blocks (`<% if ... %>...<% else %>...<% endif %>`) depending on the result of the condition.
     * @return string The parsed string
     * @param string $string String to parse
     * @param array $arguments Array of arguments from the template
     * @param int $offset [optional] Offset to start searching for the condition from
     * @param string|null $pattern [optional] You can provide custom regex for the `<% if %>` tag syntax.
     */
    public static function parseConditions(string $string, array $arguments = [], int $offset = 0, ?string $pattern = null): string
    {
        $pattern = $pattern ?? static::CONDITION_PATTERN;

        while (preg_match($pattern, $string, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $result = static::getConditionResult($matches, $arguments);

            $conditionStart = (int)$matches[0][1];
            $conditionLength = strlen($matches[0][0]);
            $insideBlockStart = $conditionStart + $conditionLength;

            $hasElse = false;
            $elseStart = null;
            $conditionEnd = null;

            $nestedOffset = $insideBlockStart;
            $nestedIfCount = 0;
            $elseMatch = null;

            while (preg_match('/<%\s?(if|else|endif)\s?.*?%>\n?/m', $string, $tagMatches, PREG_OFFSET_CAPTURE, $nestedOffset)) {
                $tag = $tagMatches[1][0];
                $tagStart = (int)$tagMatches[0][1];

                if ($tag === 'if') {
                    $nestedIfCount++;
                } elseif ($tag === 'endif') {
                    if ($nestedIfCount === 0) {
                        $conditionEnd = $tagStart;
                        break;
                    }
                    $nestedIfCount--;
                } elseif ($tag === 'else' && $nestedIfCount === 0) {
                    $hasElse = true;
                    $elseStart = $tagStart;
                    $elseMatch = $tagMatches[0][0];
                }

                $nestedOffset = $tagStart + strlen($tagMatches[0][0]);
            }

            if ($conditionEnd === null) {
                throw new RuntimeException("Missing <% endif %> tag.");
            }

            $replaceLength = $conditionEnd - $conditionStart + strlen($tagMatches[0][0]);

            if ($hasElse) {
                $elseTagLength = strlen($elseMatch); // Correctly calculate the length of the `<% else %>` tag
                $insideBlock = substr($string, $insideBlockStart, $elseStart - $insideBlockStart);
                $elseBlock = substr($string, $elseStart + $elseTagLength, $conditionEnd - ($elseStart + $elseTagLength));
                $string = substr_replace($string, $result ? $insideBlock : $elseBlock, $conditionStart, $replaceLength);
            } else {
                $insideBlock = substr($string, $insideBlockStart, $conditionEnd - $insideBlockStart);
                $string = substr_replace($string, $result ? $insideBlock : '', $conditionStart, $replaceLength);
            }

            $offset = $conditionStart;
        }

        return $string;
    }

    public static function removeComments(string $string, ?string $pattern = null): string
    {
        return preg_replace($pattern ?? static::COMMENT_PATTERN, '', $string);
    }

    /**
     * Parses a file to a string, replacing all template variables with the provided `$arguments` values.
     * @deprecated 3.2.0 Use {@see Parser::parse()} instead
     * @return string The parsed string
     * @param string $filename
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function parseFileToString(string $filename, array $arguments = [], PatternsOption $patterns = new PatternsOption()): string
    {
        if (!file_exists($filename))
            throw new RuntimeException("File '$filename' does not exist.");

        $contents = file_get_contents($filename);
        return static::parseString($contents, $arguments, false, $patterns);
    }

    /**
     * Converts a YAML, JSON or NEON file to a corresponding PHP object.
     * @deprecated 3.2.0 __Will be removed in the next version.__ This was used for parsing JSON/YAML/NEON templates in v1 and is no longer needed in v2 and later.
     * @return object
     * @param string $filename
     * @param string|null $contents [optional] You can also provide the file's content as a string, but you still have to provide a `$filename` to know which format to parse (YAML, JSON or NEON).
     */
    public static function decodeByExtension(string $filename, ?string $contents = null): object
    {
        if (file_exists($filename)) {
            $file = new SplFileObject($filename);

            $extension = $file->getExtension();
        } else {
            if (!$contents)
                throw new RuntimeException("File '$filename' doesn't exist and no contents provided.");

            $matched = preg_match('/^.+?\.(json|yml|yaml|neon)$/', $filename, $matches);
            if (!$matched)
                throw new RuntimeException("Couldn't get extension from filename '$filename'.");

            $extension = $matches[1];
        }

        switch ($extension) {
            case 'yml':
            case 'yaml':
                $parsed = $contents ? Yaml::parse($contents, Yaml::PARSE_OBJECT_FOR_MAP) : Yaml::parseFile($filename, Yaml::PARSE_OBJECT_FOR_MAP);
                break;
            case 'neon':
                $parsed = $contents ? Neon::decode($contents, true) : Neon::decodeFile($filename, true);
                break;
            case 'json':
                $parsed = $contents ? json_decode($contents) : json_decode(file_get_contents($filename));
                break;
            default:
                throw new InvalidArgumentException("Unsupported extension value '{$extension}'.");
        }

        return $parsed;
    }

    /**
     * Validate the file against a template schema and return the result
     * @return boolean True if the file is a valid template, false otherwise.
     * @param string $filename
     * @param string|null $contents [optional] You can also provide the file's content as a string, but you still have to provide a `$filename` to know which format to parse (YAML, JSON or NEON).
     * @deprecated 3.2.0 __Will be removed in the next version.__ This was used for checking JSON/YAML/NEON templates in v1 and is no longer needed in v2 and later.
     */
    public static function isValid(string $filename, ?string $contents = null): bool
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (!in_array($extension, ['yml', 'yaml', 'json', 'neon'])) {
            try {
                $arguments = static::getArgumentsWithDefaults($contents ?? file_get_contents($filename));
                $parsed = static::parseString($contents ?? file_get_contents($filename), $arguments);
            } catch (Exception $e) {
                return false;
            }

            return true;
        }

        $validator = new Validator();
        try {
            $schema = file_get_contents('https://mtrgen.matronator.cz/storage/schemas/template/latest/mtrgen-template-schema.json');
        } catch (Exception $e) {
            try {
                $schema = file_get_contents('https://files.matronator.cz/public/mtrgen/latest/mtrgen-template-schema.json');
            } catch (Exception $e) {
                throw new RuntimeException("Failed to get template schema from remote server: " . $e->getMessage());
            }
        }

        return $validator->validate($contents ?? file_get_contents($filename), $schema)->isValid();
    }

    /**
     * Validate the file against a template bundle schema and return the result
     * @return boolean True if the file is a valid bundle, false otherwise.
     * @param string $filename
     * @param string|null $contents [optional] You can also provide the file's content as a string, but you still have to provide a `$filename` to know which format to parse (YAML, JSON or NEON).
     */
    public static function isValidBundle(string $filename, ?string $contents = null): bool
    {
        if (!preg_match('/^.+?(\.bundle)\..+?$/', $filename, $matches)) {
            return false;
        }

        if (!$contents && !file_exists($filename)) {
            throw new RuntimeException("File '$filename' does not exist and no content provided.");
        }

        $validator = new Validator();
        $schema = file_get_contents('https://www.mtrgen.com/storage/schemas/bundle/latest/mtrgen-bundle-schema.json');
        return $validator->validate($contents ?? file_get_contents($filename), $schema)->isValid();
    }

    /**
     * Find all (unique) variables in the `$string` template and return them as array with optional default values.
     * @return object{arguments: array, defaults: array}
     * @param string $string String to parse.
     * @param string|null $pattern $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function getArguments(string $string, ?string $pattern = null): object
    {
        preg_match_all($pattern ?? static::VARIABLE_PATTERN, $string, $matches);

        $defaults = [];
        foreach ($matches[1] as $key => $match) {
            if ($matches[2][$key] !== '') {
                $default = static::getDefaultValue($matches[2][$key]);
                if ($default !== static::LITERALLY_NULL) {
                    $defaults[$key] = $default;
                }
            }
        }

        return (object) [
            'arguments' => static::removeDuplicates($matches[1]),
            'defaults' => $defaults,
        ];
    }

    /**
     * Find all (unique) variables in the `$string` template and return them as array with default values or null if no default is provided.
     * @return array
     * @param string $string String to parse.
     * @param string|null $pattern $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function getArgumentsWithDefaults(string $string, ?string $pattern = null): array
    {
        $arguments = static::getArguments($string, $pattern);

        $args = [];
        foreach ($arguments->arguments as $key => $arg) {
            $args[$arg] = $arguments->defaults[$key] ?? null;
        }

        return $args;
    }

    public static function getTemplateDefaults(string $string, ?string $pattern = null): array
    {
        preg_match_all($pattern ?? static::VARIABLE_PATTERN, $string, $matches);

        $defaults = [];
        foreach ($matches[1] as $key => $match) {
            if ($matches[2][$key] !== '') {
                $default = static::getDefaultValue($matches[2][$key]);
                if ($default !== static::LITERALLY_NULL) {
                    $defaults[$key] = $default;
                }
            }
        }

        return $defaults;
    }

    public static function getTemplateVariables(string $string, ?string $pattern = null): array
    {
        preg_match_all($pattern ?? static::VARIABLE_PATTERN, $string, $matches);

        return static::removeDuplicates($matches[1]);
    }

    /**
     * Apply filter functions from the template to the matching argument values.
     * @return array Modified `$arguments` array, with each matched filter function applied to the corresponding argument value.
     * @param array $matches The matches array from a `preg_match_all` function
     * @param array $arguments Array with the arguments (variables) from the template
     */
    public static function applyFilters(array $matches, array $arguments): array
    {
        $modified = $arguments;

        foreach ($arguments as $key => $arg) {
            if ($matches[4][$key]) {
                $filter = $matches[4][$key];
                $matches[3][$key] = ltrim($matches[3][$key], '|');
                if ($matches[3][$key] && $matches[3][$key] !== $filter) {
                    $filterWithArgs = explode(':', $matches[3][$key]);
                    $args = explode(',', $filterWithArgs[1]);
                    $args = array_map(function ($item) {
                        if (is_numeric($item) && !preg_match('/([\'"])/', $item)) {
                            return strpos($item, '.') === false ? (int) $item : (float) $item;
                        }
                        if (in_array($item, ['false', 'true'])) {
                            return (bool) $item;
                        }
                        if ($item === 'null') {
                            return null;
                        }

                        return (string) trim($item, '\'"`');
                    }, $args);
                    array_unshift($args, $arg);
                } else {
                    $args = [$arg];
                }

                if (method_exists(Filters::class, $filter)) {
                    $modified[$key] = Filters::$filter(...$args);
                } else {
                    if (function_exists($filter)) {
                        $modified[$key] = $filter(...$args);
                    } else {
                        throw new RuntimeException("Filter function '$filter' does not exist.");
                    }
                }
            }
        }

        return $modified;
    }

    /**
     * Check if the `$string` template needs any arguments to be parsed.
     * @return boolean True if the template needs arguments, false otherwise.
     * @param string $string String to parse.
     * @param string|null $pattern $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function needsArguments(string $string, ?string $pattern = null): bool
    {
        preg_match_all($pattern ?? static::VARIABLE_PATTERN, $string, $matches);
        foreach ($matches[2] as $match) {
            if ($match === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the result of the condition.
     * @param array $matches The matches array from a `preg_match` function
     * @param array $arguments Array with the arguments (variables) from the template
     * @return bool The result of the condition
     */
    protected static function getConditionResult(array $matches, array $arguments = []): bool
    {
        $left = $matches['left'][0];
        $negation = $matches['negation'][0] ?? null;
        $operator = $matches['operator'][0] ?? null;
        $right = $matches['value'][0] ?? null;

        $left = static::transformConditionValue($left, $arguments);
        if ($negation === '!') {
            $left = !$left;
        }

        if (isset($right)) {
            $right = static::transformConditionValue($right, $arguments);
            $result = static::getResultByOperator($left, $operator, $right);
        } else {
            $result = $left;
        }

        return $result;
    }

    /**
     * Transform the value of a condition to the correct type.
     * @param string $value The value to transform
     * @param array $arguments Array with the arguments (variables) from the template
     * @return mixed The transformed value
     */
    protected static function transformConditionValue(string $value, array $arguments = []): mixed
    {
        $valueNegated = false;
        if (str_starts_with($value, '!')) {
            $valueNegated = true;
            $value = substr($value, 1);
        }

        if (str_starts_with($value, '$')) {
            $value = substr($value, 1);
            if (!array_key_exists($value, $arguments)) {
                throw new RuntimeException("Variable '$value' not found in arguments.");
            }

            $value = $arguments[$value];
        } else {
            $value = static::convertArgumentType($value);
        }

        return $valueNegated ? !$value : $value;
    }

    /**
     * Convert the argument value to the correct type.
     * @param string $value The value to convert
     * @return mixed The converted value
     */
    protected static function convertArgumentType(string $value): mixed
    {
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        } else if ($value === 'true') {
            return true;
        } else if ($value === 'false') {
            return false;
        } else if ($value === 'null') {
            return null;
        } else if (str_contains($value, '.')) {
            return floatval($value);
        } else if (is_numeric($value) && !str_contains($value, '.')) {
            return intval($value);
        }

        return (string)$value;
    }

    /**
     * Get the result of the comparison between the left and right values using the operator.
     * @param mixed $left The left side of the comparison
     * @param string|null $operator The operator to use for the comparison
     * @param mixed $right The right side of the comparison
     * @return bool The result of the comparison
     */
    protected static function getResultByOperator(mixed $left, ?string $operator, mixed $right): bool
    {
        switch ($operator) {
            case '==':
                return $left == $right;
            case '===':
                return $left === $right;
            case '!=':
                return $left != $right;
            case '!==':
                return $left !== $right;
            case '<':
                return $left < $right;
            case '<=':
                return $left <= $right;
            case '>':
                return $left > $right;
            case '>=':
                return $left >= $right;
            case null:
                return $left;
            default:
                throw new RuntimeException("Unsupported operator '$operator'.");
        }
    }

    /**
     * @param string $defaultMatch The default value match
     * @return mixed The default value
     */
    protected static function getDefaultValue(string $defaultMatch): mixed
    {
        $default = ltrim($defaultMatch, '=') ?? null;

        if ($default === null) {
            return static::LITERALLY_NULL;
        }

        return static::convertArgumentType($default);
    }

    private static function removeDuplicates(array $array): array
    {
        $uniqueValues = [];
        foreach ($array as $value) {
            if (!in_array($value, $uniqueValues)) {
                $uniqueValues[] = $value;
            }
        }
        return $uniqueValues;
    }
}
