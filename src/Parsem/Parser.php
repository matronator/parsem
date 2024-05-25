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

final class Parser
{
    // Old pattern for backup
    // public const PATTERN = '/<%\s?([a-zA-Z0-9_]+)\|?([a-zA-Z0-9_]+?)?\s?%>/m';

    /**
     * Matches:
     * 0: <% var='default'|filter:10,'arg','another' %> --> (full match)
     * 1: var                                           --> (only variable name)
     * 2: ='default'                                    --> (default value)
     * 3: filter:10,'arg','another'                     --> (filter with args)
     * 4: filter                                        --> (only filter name)
     */
    public const VARIABLE_PATTERN = '/<%\s?([a-zA-Z0-9_]+)(=.+?)?\|?(([a-zA-Z0-9_]+?)(?:\:(?:(?:\\?\'|\\?")?.?(?:\\?\'|\\?")?,?)+?)*?)?\s?%>/m';

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

    public const LITERALLY_NULL = '__:-LITERALLY_NULL-:__';

    /**
     * Parses a string, replacing all template variables with the corresponding values passed in `$arguments`.
     * @return mixed The parsed string or the original `$string` value if it's not string
     * @param mixed $string String to parse. If not provided with a string, the function will return this value
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function parseString(mixed $string, array $arguments = [], ?string $pattern = null): mixed
    {
        if (!is_string($string)) return $string;

        $string = self::parseConditions($string, $arguments);

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
                    $args[] = null;
                }
            }
        }

        $args = static::applyFilters($matches, $args);

        return str_replace($matches[0], $args, $string);
    }

    public static function parseConditions(string $string, array $arguments = [], int $offset = 0): string
    {
        preg_match(static::CONDITION_PATTERN, $string, $matches, PREG_OFFSET_CAPTURE, $offset);
        if (!$matches) {
            return $string;
        }

        $left = $matches['left'][0];
        $negation = $matches['negation'][0] ?? null;
        $operator = $matches['operator'][0] ?? null;
        $right = $matches['value'][0] ?? null;

        if (str_starts_with($left, '$')) {
            $left = substr($left, 1);
            if (!isset($arguments[$left])) {
                throw new RuntimeException("Variable '$left' not found in arguments.");
            }

            if ($negation === '!') {
                $left = !$arguments[$left];
            } else {
                $left = $arguments[$left];
            }
        } else {
            if ((str_starts_with($left, '"') && str_ends_with($left, '"')) || (str_starts_with($left, "'") && str_ends_with($left, "'"))) {
                $left = substr($left, 1, -1);
            } else if ($left === 'true') {
                $left = true;
            } else if ($left === 'false') {
                $left = false;
            } else if ($left === 'null') {
                $left = null;
            } else if (str_contains($left, '.')) {
                $left = floatval($left);
            } else if (is_numeric($left) && !str_contains($left, '.')) {
                $left = intval($left);
            } else {
                $left = (string)$left;
            }
        }

        if (isset($right)) {
            $rightNegated = false;

            if (str_starts_with($right, '!')) {
                $rightNegated = true;
                $right = substr($right, 1);
            }
    
            if (str_starts_with($right, '$')) {
                $right = substr($right, 1);
                if (!isset($arguments[$right])) {
                    throw new RuntimeException("Variable '$right' not found in arguments.");
                }
    
                $right = $arguments[$right];
            } else {
                if ((str_starts_with($right, '"') && str_ends_with($right, '"')) || (str_starts_with($right, "'") && str_ends_with($right, "'"))) {
                    $right = substr($right, 1, -1);
                } else if ($right === 'true') {
                    $right = true;
                } else if ($right === 'false') {
                    $right = false;
                } else if ($right === 'null') {
                    $right = null;
                } else if (str_contains($right, '.')) {
                    $right = floatval($right);
                } else if (is_numeric($right) && !str_contains($right, '.')) {
                    $right = intval($right);
                } else {
                    $right = (string)$right;
                }
            }

            if ($rightNegated) {
                $right = !$right;
            }

            if ($operator === '==') {
                $result = $left == $right;
            } elseif ($operator === '===') {
                $result = $left === $right;
            } elseif ($operator === '!=') {
                $result = $left != $right;
            } elseif ($operator === '!==') {
                $result = $left !== $right;
            } elseif ($operator === '<') {
                $result = $left < $right;
            } elseif ($operator === '<=') {
                $result = $left <= $right;
            } elseif ($operator === '>') {
                $result = $left > $right;
            } elseif ($operator === '>=') {
                $result = $left >= $right;
            } else if (!isset($operator)) {
                $result = $left;
            } else {
                throw new RuntimeException("Unsupported operator '$operator'.");
            }
        } else {
            $result = $left;
        }

        preg_match('/<%\s?endif\s?%>\n?/', $string, $endMatches, PREG_OFFSET_CAPTURE, $offset);
        if (!$endMatches) {
            throw new RuntimeException("Missing <% endif %> tag.");
        }

        $insideBlock = substr($string, $matches[0][1] + strlen($matches[0][0]), $endMatches[0][1] - $matches[0][1] - strlen($matches[0][0]));
        $string = substr_replace($string, $result ? $insideBlock : '', (int)$matches[0][1], $endMatches[0][1] - $matches[0][1] + strlen($endMatches[0][0]));

        preg_match(static::CONDITION_PATTERN, $string, $matches, PREG_OFFSET_CAPTURE, $offset);
        if (!$matches) {
            return $string;
        }

        return self::parseConditions($string, $arguments, (int)$matches[0][1]);
    }

    /**
     * Converts a YAML, JSON or NEON file to a corresponding PHP object, replacing all template variables with the provided `$arguments` values.
     * @return object
     * @param string $filename
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function parseFile(string $filename, array $arguments = [], ?string $pattern = null): object
    {
        return self::decodeByExtension($filename, self::parseFileToString($filename, $arguments, $pattern));
    }

    public static function parseFileToString(string $filename, array $arguments = [], ?string $pattern = null): string
    {
        if (!file_exists($filename))
            throw new RuntimeException("File '$filename' does not exist.");

        $contents = file_get_contents($filename);
        return self::parseString($contents, $arguments, $pattern);
    }

    /**
     * Converts a YAML, JSON or NEON file to a corresponding PHP object.
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

        return $function(self::parseFileToString($filename, $arguments, $pattern));
    }

    /**
     * Validate the file against a template schema and return the result
     * @return boolean True if the file is a valid template, false otherwise.
     * @param string $filename
     * @param string|null $contents [optional] You can also provide the file's content as a string, but you still have to provide a `$filename` to know which format to parse (YAML, JSON or NEON).
     */
    public static function isValid(string $filename, ?string $contents = null): bool
    {
        try {
            $parsed = self::decodeByExtension($filename, $contents);
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
            $parsed = self::decodeByExtension($filename, $contents);
        } catch (Exception $e) {
            return false;
        }

        $validator = new Validator();
        $schema = file_get_contents('https://www.mtrgen.com/storage/schemas/bundle/latest/mtrgen-bundle-schema.json');
        return $validator->validate($parsed, $schema)->isValid();
    }

    /**
     * Find all (unique) variables in the `$string` template and return them as array with optional default values.
     * @return object
     * @param string $string String to parse.
     * @param string|null $pattern $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function getArguments(string $string, ?string $pattern = null): object
    {
        preg_match_all($pattern ?? self::VARIABLE_PATTERN, $string, $matches);

        $arguments = static::removeDuplicates($matches[1]);
        $defaults = [];

        foreach ($matches[1] as $key => $match) {
            if ($matches[2][$key] !== '') {
                $default = self::getDefaultValue($matches[2][$key]);
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

    public static function needsArguments(string $string, ?string $pattern = null): bool
    {
        preg_match_all($pattern ?? self::VARIABLE_PATTERN, $string, $matches);
        foreach ($matches[2] as $match) {
            if ($match === '') {
                return true;
            }
        }

        return false;
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

    /**
     * @param string $defaultMatch
     * @param array $defaults
     * @return array
     */
    private static function getDefaultValue(string $defaultMatch): mixed
    {
        $default = trim($defaultMatch, '=') ?? null;

        if (!$default) {
            return static::LITERALLY_NULL;
        }

        if (is_numeric($default) && !preg_match('/([\'"`])/', $default)) {
            return strpos($default, '.') === false ? (int)$default : (float)$default;
        } else if (in_array($default, ['false', 'true'])) {
            return (bool)trim($default, '\'"`');
        } else if ($default === 'null') {
            return null;
        } else {
            return (string)trim($default, '\'"`\\');
        }
    }
}
