<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class DeepNestedData extends Data
{
    public function __construct(
        #[Required, StringType] public readonly string $title,

        /** @var MiddleLevelData[] */
        #[DataCollectionOf(MiddleLevelData::class)] public readonly array $sections = []
    ) {
    }
}
