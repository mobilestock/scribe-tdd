<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Data;

class SimpleData extends Data
{
    public function __construct(public string $name, public int $quantity = 1)
    {
    }
}
