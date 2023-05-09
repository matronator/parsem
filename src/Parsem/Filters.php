<?php

declare(strict_types=1);

namespace Matronator\Parsem;

class Filters
{
    public const ENCODING = 'UTF-8';

    public const GLOBAL_FILTERS = ['upper', 'lower', 'upperFirst', 'lowerFirst', 'first', 'last', 'camelCase', 'snakeCase', 'kebabCase', 'pascalCase', 'titleCase', 'length', 'reverse', 'random', 'truncate'];

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
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        return $string;
    }

    public static function snakeCase(string $string): string
    {
        $string = preg_replace('/([a-z])([A-Z])/', '$1_$2', $string);
        $string = strtolower($string);
        return $string;
    }

    public static function kebabCase(string $string): string
    {
        $string = preg_replace('/([a-z])([A-Z])/', '$1-$2', $string);
        $string = strtolower($string);
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

    public static function length(string $string): int
    {
        return mb_strlen($string, static::ENCODING);
    }

    public static function reverse(string $string): string
    {
        return strrev($string);
    }

    public static function random(array|string $array): mixed
    {
        if (is_string($array))
            return $array[rand(0, strlen($array) - 1)];
        
        return $array[array_rand($array)];
    }

    public static function truncate(string $string, int $length, string $ending = '...'): string
    {
        return mb_substr($string, 0, $length, static::ENCODING) . $ending;
    }
}
