<?php

namespace AjCastro\ScribeTdd\Writing;

use Illuminate\Support\Arr;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

class ScribeTddOverridesGenerator extends OpenApiGenerator
{
    public function root(array $root, array $groupedEndpoints): array
    {
        $overrides = $this->config->get('openapi.overrides', []);

        return array_replace_recursive($root, Arr::undot($overrides));
    }
}
