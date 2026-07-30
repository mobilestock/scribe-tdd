<?php

namespace AjCastro\ScribeTdd\Strategies\ResponseFields;

use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag;

class GetFromResponseFieldTagFromScribeTdd extends GetFromResponseFieldTag
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $testResult = RouteTestResult::getTestResultForRoute($endpointData->route);

        if (empty($testResult)) {
            return [];
        }

        [
            'method' => $methodDocBlock,
            'class' => $classDocBlock
        ]
        = RouteTestResult::getTestDocBlocks($endpointData->route, $testResult);
    
        return $this->getFromTags($methodDocBlock->getTags(), $classDocBlock?->getTags() ?: []);
    }
}
