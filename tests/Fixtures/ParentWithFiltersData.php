<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ParentWithFiltersData extends Data
{
    public function __construct(
        #[Required, StringType] public readonly string $name,

        /** @var FilterItemData[] */
        #[DataCollectionOf(FilterItemData::class)] public readonly array $filters = []
    ) {
    }
}
