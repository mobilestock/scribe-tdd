<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Data;

class NestedItemData extends Data
{
    public function __construct(public string $sku, public int $qty = 1)
    {
    }
}
