<?php

namespace AjCastro\ScribeTdd\Strategies\QueryParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromInlineValidator as BaseGetFromInlineValidator;
use PhpParser\Node;

class GetFromInlineValidator extends BaseGetFromInlineValidator
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        if (!in_array('GET', $endpointData->route->methods())) {
            return [];
        }

        return parent::__invoke($endpointData, $routeRules);
    }

    protected function isValidationStatementMeantForThisStrategy(Node $validationStatement): bool
    {
        return true;
    }
}
