<?php

namespace AjCastro\ScribeTdd\Strategies\BodyParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromInlineValidator as BaseGetFromInlineValidator;
use PhpParser\Node;

class GetFromInlineValidator extends BaseGetFromInlineValidator
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $this->endpointData = $endpointData;
        return parent::__invoke($endpointData, $routeRules);
    }

    protected function isValidationStatementMeantForThisStrategy(Node $validationStatement): bool
    {
        return !in_array('GET', $this->endpointData->route->methods());
    }
}
