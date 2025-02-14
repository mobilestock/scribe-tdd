<?php

namespace AjCastro\ScribeTdd\Strategies\BodyParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromInlineValidator as BaseGetFromInlineValidator;

class GetFromInlineValidator extends BaseGetFromInlineValidator
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        if (in_array('GET', $endpointData->route->methods())) {
            return [];
        }

        return parent::__invoke($endpointData, $routeRules);
    }
}
