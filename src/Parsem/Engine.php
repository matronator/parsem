<?php

declare(strict_types=1);

namespace Matronator\Parsem;

use Matronator\Parsem\Config\Options;
use Matronator\Parsem\Config\PatternsOption;

class Engine
{
    private bool $strict = true;
    private bool $trimBeforeBlocks = false;
    private bool $trimAfterBlocks = false;
    private PatternsOption $patterns = new PatternsOption();

    public function __construct(Options|array|null $options = new Options())
    {
        $this->setOptions($options);
    }

    public function setOptions(Options|array|null $options = new Options()): void
    {
        if (!($options instanceof Options)) {
            if (is_array($options)) {
                $options = new Options(...$options);
            } else {
                throw new \InvalidArgumentException('Options must be an instance of Options class or an array.');
            }
        }

        $this->strict = $options->strict ?? $this->strict;
        $this->patterns = $options->patterns ?? $this->patterns;
        $this->trimBeforeBlocks = $options->trimBeforeBlocks ?? $this->trimBeforeBlocks;
        $this->trimAfterBlocks = $options->trimAfterBlocks ?? $this->trimAfterBlocks;
    }

    public function getOptions(): Options
    {
        return new Options(
            $this->strict,
            $this->patterns,
            $this->trimBeforeBlocks,
            $this->trimAfterBlocks,
        );
    }

    public function parse(string $input, array $arguments = []): string
    {
        return Parser::parseString($input, $arguments, $this->strict, $this->patterns);
    }
}
