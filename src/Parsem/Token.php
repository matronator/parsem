<?php

declare(strict_types=1);

namespace Matronator\Parsem;

class Token
{
    public string $match;

    public string $variable;

    public ?string $filter = null;

    public function __construct(string $match, string $variable, ?string $filter = null)
    {
        $this->match = $match;
        $this->variable = $variable;
        $this->filter = $filter;
    }

    public static function fromArray(array $array): Token
    {
        return new self($array[0], $array[1], isset($array[2]) ? $array[2] : null);
    }

    /**
     * Returns an array of tokens made from multi-dimensional array, like the one returned by `preg_match_all`.
     *
     * @param array ...$arrays
     * @return Token[]
     */
    public static function fromMultiArray(array ...$arrays): array
    {
        $return = [];

        foreach ($arrays as $array) {
            if (!is_array($array)) continue;

            $return[] = self::fromArray($array);
        }

        return $return;
    }
}