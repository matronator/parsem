<?php

declare(strict_types=1);

namespace Matronator\Parsem;

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
        'truncate',
        'escape', 'unescape', 'hash'];

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

    public static function first(string $string): string
    {
        return mb_substr($string, 0, 1, static::ENCODING);
    }

    public static function last(string $string): string
    {
        return mb_substr($string, -1, 1, static::ENCODING);
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
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
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

    public static function random(array|string $value): array|string
    {
        if (is_string($value)) {
            return $value[rand(0, strlen($value) - 1)];
        }

        return $value[array_rand($value)];
    }

    public static function shuffle(array|string $value): array|string
    {
        $array = is_string($value) ? str_split($value) : $value;
        shuffle($array);

        return $value;
    }

    public static function truncate(string $string, int $length, string $ending = '...'): string
    {
        if (mb_strlen($string, static::ENCODING) <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length, static::ENCODING) . $ending;
    }
}
