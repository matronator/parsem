<?php

declare(strict_types=1);

namespace Matronator\Parsem;

use Exception;
use InvalidArgumentException;
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
    public const COMMENT_PATTERN = '/<#\s?(.+?)\s?#>/m';

    /** @internal */
    private const LITERALLY_NULL = '⚠︎__:-␀LITERALLY_NULL␀-:__⚠︎';

    /**
     * Parses a file to a PHP object, replacing all template variables with the provided `$arguments` values.
     * @since 3.2.0
     * @return string The parsed string
     * @param string $filename File to parse
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param bool $strict [optional] If set to `true`, the function will throw an exception if a variable is not found in the `$arguments` array. If set to `false` null will be used.
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     * @throws RuntimeException If the file does not exist
     */
    public static function parse(string $filename, array $arguments = [], bool $strict = true, ?string $pattern = null): string
    {
        if (!file_exists($filename))
            throw new RuntimeException("File '$filename' does not exist.");

        $contents = file_get_contents($filename);
        return static::parseString($contents, $arguments, $strict, $pattern);
    }

    /**
     * Parses a string, replacing all template variables with the corresponding values passed in `$arguments`.
     * @return mixed The parsed string or the original `$string` value if it's not string
     * @param mixed $string String to parse. If not provided with a string, the function will return this value
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param bool $strict [optional] If set to `true`, the function will throw an exception if a variable is not found in the `$arguments` array. If set to `false` null will be used.
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     * @throws RuntimeException If a variable is not found in the `$arguments` array and `$strict` is set to `true`
     */
    public static function parseString(mixed $string, array $arguments = [], bool $strict = true, ?string $pattern = null): mixed
    {
        if (!is_string($string)) return $string;

        $string = static::removeComments($string);
        $string = static::parseConditions($string, $arguments);

        preg_match_all($pattern ?? static::VARIABLE_PATTERN, $string, $matches);
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
        preg_match($pattern ?? static::CONDITION_PATTERN, $string, $matches, PREG_OFFSET_CAPTURE, $offset);
        if (!$matches) {
            return $string;
        }

        $result = static::getConditionResult($matches, $arguments);

        $conditionStart = (int)$matches[0][1];
        $conditionLength = strlen($matches[0][0]);
        $insideBlockStart = $conditionStart + $conditionLength;

        $hasElse = false;
        $elseCount = preg_match('/<%\s?else\s?%>\n?/', $string, $elseMatches, PREG_OFFSET_CAPTURE, $offset);
        if ($elseCount !== false && $elseCount === 1 && $elseMatches) {
            $hasElse = true;
            $elseStart = (int) $elseMatches[0][1];
            $elseTagLength = strlen($elseMatches[0][0]);
            $elseBlock = static::parseElseTag($string, $elseStart, $elseTagLength, $arguments, $offset);
        } else if ($elseCount > 1) {
            throw new RuntimeException("Too many <% else %> tags.");
        } else {
            $string = static::parseNestedIfs($string, $arguments, $offset + $conditionLength, $insideBlockStart);
        }

        preg_match('/<%\s?endif\s?%>\n?/', $string, $endMatches, PREG_OFFSET_CAPTURE, $offset);
        if (!$endMatches) {
            throw new RuntimeException("Missing <% endif %> tag.");
        }

        $conditionEnd = $endMatches[0][1];
        $replaceLength = $conditionEnd - $conditionStart + strlen($endMatches[0][0]);

        if ($hasElse) {
            $insideBlock = substr($string, $insideBlockStart, $elseStart - $insideBlockStart);
            $string = substr_replace($string, $result ? $insideBlock : $elseBlock, $conditionStart, $replaceLength);
        } else {
            $insideBlock = substr($string, $insideBlockStart, $conditionEnd - $insideBlockStart);
            $string = substr_replace($string, $result ? $insideBlock : '', $conditionStart, $replaceLength);
        }

        return static::parseConditions($string, $arguments, $conditionStart);
    }

    public static function removeComments(string $string): string
    {
        return preg_replace(static::COMMENT_PATTERN, '', $string);
    }

    /**
     * Converts a YAML, JSON or NEON file to a corresponding PHP object, replacing all template variables with the provided `$arguments` values.
     * @deprecated 3.2.0 __Will be removed in the next version.__ This was used for parsing JSON/YAML/NEON templates in v1 and is no longer needed in v2 and later.
     * @return object
     * @param string $filename
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function parseFile(string $filename, array $arguments = [], ?string $pattern = null): object
    {
        return static::decodeByExtension($filename, static::parseFileToString($filename, $arguments, $pattern));
    }

    /**
     * Parses a file to a string, replacing all template variables with the provided `$arguments` values.
     * @deprecated 3.2.0 Use {@see Parser::parse()} instead
     * @return string The parsed string
     * @param string $filename
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function parseFileToString(string $filename, array $arguments = [], ?string $pattern = null): string
    {
        if (!file_exists($filename))
            throw new RuntimeException("File '$filename' does not exist.");

        $contents = file_get_contents($filename);
        return static::parseString($contents, $arguments, false, $pattern);
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
     * Parse a file of any type to object using a custom provided parser function.
     * @deprecated 3.2.O __Will be removed in the next version.__ This was used for parsing JSON/YAML/NEON templates in v1 and is no longer needed in v2 and later.
     * @return object
     * @param string $filename
     * @param callable $function The parsing function with the following signature `function(string $contents): object` where `$contents` will be the string content of `$filename`.
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function customParse(string $filename, callable $function, array $arguments = [], ?string $pattern = null): object
    {
        if (!file_exists($filename))
            throw new RuntimeException("File '$filename' does not exist.");

        if (!is_callable($function))
            throw new InvalidArgumentException("Argument \$function is not callable.");

        return $function(static::parseFileToString($filename, $arguments, $pattern));
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
        try {
            $parsed = static::decodeByExtension($filename, $contents);
        } catch (Exception $e) {
            return false;
        }

        $validator = new Validator();
        try {
            $schema = file_get_contents('https://www.mtrgen.com/storage/schemas/template/latest/mtrgen-template-schema.json');
        } catch (Exception $e) {
            try {
                $schema = file_get_contents('https://files.matronator.cz/public/mtrgen/latest/mtrgen-template-schema.json');
            } catch (Exception $e) {
                throw new RuntimeException("Failed to get template schema from remote server: " . $e->getMessage());
            }
        }

        return $validator->validate($parsed, $schema)->isValid();
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

        try {
            $parsed = static::decodeByExtension($filename, $contents);
        } catch (Exception $e) {
            return false;
        }

        $validator = new Validator();
        $schema = file_get_contents('https://www.mtrgen.com/storage/schemas/bundle/latest/mtrgen-bundle-schema.json');
        return $validator->validate($parsed, $schema)->isValid();
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

        $arguments = static::removeDuplicates($matches[1]);
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
            'arguments' => $arguments,
            'defaults' => $defaults,
        ];
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
     * Parse the else block of the condition.
     * @param string &$string The string to parse (by reference - will be modified in place)
     * @param int $elseStart The position of the else tag in the string
     * @param int $elseTagLength The length of the else tag
     * @param array $arguments Array with the arguments (variables) from the template
     * @param int $offset Offset to start replacing the condition from
     * @return string The parsed else block
     */
    protected static function parseElseTag(string &$string, int $elseStart, int $elseTagLength, array $arguments = [], $offset = 0): string
    {
        $string = static::parseNestedIfs($string, $arguments, $elseStart + $elseTagLength, (int)$elseStart + $elseTagLength);

        preg_match('/<%\s?endif\s?%>\n?/', $string, $endMatches, PREG_OFFSET_CAPTURE, $offset);
        if (!$endMatches) {
            throw new RuntimeException("Missing <% endif %> tag.");
        }

        $conditionEnd = $endMatches[0][1];

        $elseBlock = substr($string, $elseStart + $elseTagLength, $conditionEnd - $elseStart - $elseTagLength);

        return $elseBlock;
    }

    /**
     * Parse nested if conditions.
     * @param string $string The string to parse
     * @param array $arguments Array with the arguments (variables) from the template
     * @param int $searchOffset Offset to start searching for the condition from
     * @param int $replaceOffset Offset to start replacing the condition from
     * @return string The parsed string
     */
    protected static function parseNestedIfs(string $string, array $arguments = [], int $searchOffset = 0, int $replaceOffset = 0): string
    {
        $nestedIfs = preg_match_all(static::CONDITION_PATTERN, $string, $nestedMatches, PREG_OFFSET_CAPTURE, $searchOffset);
        if ($nestedIfs !== false && $nestedIfs > 0) {
            $string = static::parseConditions($string, $arguments, $replaceOffset);
        }

        return $string;
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
            if (in_array($value, $uniqueValues)) {
                $value = null;
            } else {
                $uniqueValues[] = $value;
            }
        }
        return $uniqueValues;
    }
}
