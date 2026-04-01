<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Data;

class MixedParamData extends Data
{
    public function __construct(
        public string $name,
        public string|int $mixed_field,
    ) {
    }
}
