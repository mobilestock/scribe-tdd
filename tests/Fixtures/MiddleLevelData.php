<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class MiddleLevelData extends Data
{
    public function __construct(
        #[Required, StringType] public readonly string $label,

        public readonly NestedItemData $detail
    ) {
    }
}
