<?php

namespace Tests\Fixtures;

use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class FilterItemData extends Data
{
    public function __construct(
        #[Required] public readonly array $tags,

        #[Nullable, Enum(TestFilterType::class)] public readonly ?TestFilterType $type = null
    ) {
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'tags.*' => ['nullable', 'string'],
        ];
    }
}
