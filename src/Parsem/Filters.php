<?php

declare(strict_types=1);

namespace Matronator\Parsem;

use Symfony\Component\Yaml\Yaml;

class Filters
{
    public const ENCODING = 'UTF-8';

    public const GLOBAL_FILTERS = [
        'upper', 'lower',
        'upperFirst', 'lowerFirst',
        'first', 'last',
        'camelCase', 'snakeCase', 'kebabCase', 'pascalCase', 'titleCase',
        'length',
        'reverse', 'random', 'shuffle',
        'truncate', 'trim',
        'url', 'stripTags', 'nl2br',
        'escape', 'unescape', 'hash', 'rot13', 'encode', 'decode',
    ];

    public static function upper(string $string): string
    {
        return mb_strtoupper($string, static::ENCODING);
    }

    public static function lower(string $string): string
    {
        return mb_strtolower($string, static::ENCODING);
    }

    public static function upperFirst(string $string): string
    {
        $fc = mb_strtoupper(mb_substr($string, 0, 1, static::ENCODING), static::ENCODING);
        return $fc . mb_substr($string, 1, null, static::ENCODING);
    }

    public static function lowerFirst(string $string): string
    {
        $fc = mb_strtolower(mb_substr($string, 0, 1, static::ENCODING), static::ENCODING);
        return $fc . mb_substr($string, 1, null, static::ENCODING);
    }

    public static function first(string|array $value): mixed
    {
        if (is_array($value)) {
            return reset($value);
        }
        return mb_substr($value, 0, 1, static::ENCODING);
    }

    public static function last(string|array $value): mixed
    {
        if (is_array($value)) {
            return end($value);
        }
        return mb_substr($value, -1, 1, static::ENCODING);
    }

    public static function camelCase(string $string): string
    {
        $firstCharIsLowerCase = ctype_lower(mb_substr($string, 0, 1, static::ENCODING));
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        if ($firstCharIsLowerCase) {
            $string = static::lowerFirst($string);
        }
        return $string;
    }

    public static function snakeCase(string $string): string
    {
        $string = static::camelCase($string);
        $string = preg_replace('/([a-z])([A-Z])/', '$1_$2', $string);
        $string = str_replace([' ', '-'], '_', $string);
        $string = strtolower($string);
        return $string;
    }

    public static function kebabCase(string $string): string
    {
        $string = static::snakeCase($string);
        $string = str_replace('_', '-', $string);
        return $string;
    }

    public static function pascalCase(string $string): string
    {
        $string = static::camelCase($string);
        $string = static::upperFirst($string);
        return $string;
    }

    public static function titleCase(string $string): string
    {
        // $string = str_replace(['-', '_'], ' ', $string);
        // $split = preg_split('/(?=[A-Z])/', $string);
        // $string = implode(' ', $split);
        // $string = ucwords($string);

        // $string = static::camelCase($string);
        // $string = preg_replace('/([a-z])([A-Z])/', '$1 $2', $string);
        $string = str_replace(['_'], ' ', $string);
        $string = str_replace(['-'], ' 🜛 ', $string);
        $string = ucwords($string);
        $string = str_replace([' 🜛 '], '-', $string);
        return $string;
    }

    public static function length(array|string $value): int
    {
        return is_string($value) ? mb_strlen($value, static::ENCODING) : count($value);
    }

    public static function reverse(array|string $value): array|string
    {
        return is_string($value) ? strrev($value) : array_reverse($value);
    }

    public static function random(array|string $value): mixed
    {
        if (is_string($value)) {
            return $value[rand(0, strlen($value) - 1)];
        }

        return $value[array_rand($value)];
    }

    public static function shuffle(array|string $value): array|string
    {
        if (is_string($value)) {
            return str_shuffle($value);
        }
        $array = clone $value;
        shuffle($array);
        return $array;
    }

    public static function truncate(string $string, int $length, string $ending = '...'): string
    {
        if (mb_strlen($string, static::ENCODING) <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length, static::ENCODING) . $ending;
    }

    public static function trim(string $string, string $side = 'both', string $characters = " \n\r\t\v\0"): string
    {
        if ($side === 'left') {
            return ltrim($string, $characters, );
        } else if ($side === 'right') {
            return rtrim($string, $characters);
        }

        return trim($string, $characters);
    }

    public static function url(string $string): string
    {
        return rawurlencode($string);
    }

    public static function stripTags(string $string): string
    {
        return strip_tags($string);
    }

    public static function nl2br(string $string, bool $xhtmlSyntax = false): string
    {
        return nl2br($string, $xhtmlSyntax);
    }

    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, static::ENCODING);
    }

    public static function unescape(string $string): string
    {
        return html_entity_decode($string, ENT_QUOTES, static::ENCODING);
    }

    public static function hash(string $string, string $algorithm = 'md5', string|null $secret = null): string
    {
        if ($secret) {
            return hash_hmac($algorithm, $string, $secret);
        }
        return hash($algorithm, $string);
    }

    public static function rot13(string $string): string
    {
        return str_rot13($string);
    }

    public static function encode(string $string, string $encoding = 'base64'): string
    {
        switch ($encoding) {
            case 'base64':
                return base64_encode($string);
            case 'hex':
                return bin2hex($string);
            case 'url':
                return rawurlencode($string);
            case 'json':
                return json_encode($string);
            case 'yaml':
                return Yaml::dump($string);
            default:
                return $string;
        }
    }
}
