<?php

declare(strict_types=1);

namespace Matronator\Parsem\Config;

use ArrayObject;

class Options extends ArrayObject
{
    public function __construct(
        public readonly bool $strict = false,
        public readonly PatternsOption $patterns = new PatternsOption(),
        public readonly ?bool $trimBeforeBlocks = false,
        public readonly ?bool $trimAfterBlocks = false,
    ) { }
}
