<?php

namespace AjCastro\ScribeTdd\Strategies\QueryParameters;

use AjCastro\ScribeTdd\Attributes\DocumentateInBodyParams;
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

        $this->endpointData = $endpointData;
        return parent::__invoke($endpointData, $routeRules);
    }

    public static function shouldUseBodyParameters(ExtractedEndpointData $endpointData): bool
    {
        return !empty($endpointData->method->getAttributes(DocumentateInBodyParams::class));
    }

    protected function isValidationStatementMeantForThisStrategy(Node $validationStatement): bool
    {
        return !self::shouldUseBodyParameters($this->endpointData);
    }
}
