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
    public const PATTERN = '/<%\s?([a-zA-Z0-9_]+)\|?([a-zA-Z0-9_]+?)?\s?%>/m';

    /**
     * Parses a string, replacing all template variables with the corresponding values passed in `$arguments`.
     * @return mixed The parsed string or the original `$string` value if it's not string
     * @param mixed $string String to parse. If not provided with a string, the function will return this value
     * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
     * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function parse(mixed $string, array $arguments = [], ?string $pattern = null): mixed
    {
        if (!is_string($string)) return $string;

        preg_match_all($pattern ?? self::PATTERN, $string, $matches);
        $args = [];
        foreach ($matches[1] as $match) {
            $args[] = $arguments[$match] ?? null;
        }

        $args = self::applyFilters($matches, $args);

        return str_replace($matches[0], $args, $string);
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
        $contents = file_get_contents($filename);
        $parsed = self::parse($contents, $arguments, $pattern);
        return self::parseByExtension($filename, $parsed);
    }

    /**
     * Converts a YAML, JSON or NEON file to a corresponding PHP object.
     * @return object
     * @param string $filename
     * @param string|null $contents [optional] You can also provide the file's content as a string, but you still have to provide a `$filename` to know which format to parse (YAML, JSON or NEON).
     */
    public static function parseByExtension(string $filename, ?string $contents = null): object
    {
        if (!file_exists($filename) && !$contents)
            throw new RuntimeException("File '$filename' does not exist.");

        $file = new SplFileObject($filename);

        $extension = $file->getExtension();

        switch ($extension) {
            case 'yml':
            case 'yaml':
                $parsed = $contents ? Yaml::parse($contents, Yaml::PARSE_OBJECT_FOR_MAP) : Yaml::parseFile($filename, Yaml::PARSE_OBJECT_FOR_MAP);
                break;
            case 'neon':
                $parsed = $contents ? Neon::decode($contents) : Neon::decodeFile($filename);
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
     */
    public static function isValid(string $filename, ?string $contents = null): bool
    {
        try {
            $parsed = self::parseByExtension($filename, $contents);
        } catch (Exception $e) {
            return false;
        }

        $validator = new Validator();
        $schema = 'https://files.matronator.com/public/mtrgen/latest/mtrgen-template-schema.json';
        $result = $validator->validate($parsed, $schema);

        return $result->isValid();
    }

    /**
     * Find all (unique) variables in the `$string` template and return them as array.
     * @return array
     * @param string $string String to parse.
     * @param string|null $pattern $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
     */
    public static function getArguments(string $string, ?string $pattern = null): array
    {
        preg_match_all($pattern ?? self::PATTERN, $string, $matches);

        return array_unique($matches[1]);
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
            if ($matches[2][$key]) {
                $function = $matches[2][$key];
                if (function_exists($function)) {
                    $modified[$key] = $function($arg);
                }
            }
        }

        return $modified;
    }
}
