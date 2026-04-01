<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class OrderData extends Data
{
    public function __construct(
        public string $customer_name,
        public NestedItemData $main_item,
        /** @var NestedItemData[] */
        #[DataCollectionOf(NestedItemData::class)] public array $items = []
    ) {
    }
}
