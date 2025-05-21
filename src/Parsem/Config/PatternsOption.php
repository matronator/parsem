<?php

declare(strict_types=1);

namespace Matronator\Parsem\Config;

use ArrayObject;

class PatternsOption extends ArrayObject
{
    public function __construct(
        public ?string $variables = null,
        public ?string $conditions = null,
        public ?string $comments = null,
    ) { }
}
