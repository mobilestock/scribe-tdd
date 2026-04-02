<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class DataCollectionData extends Data
{
    public function __construct(
        public string $title,
        #[DataCollectionOf(NestedItemData::class)]
        public DataCollection $items,
    ) {
    }
}
