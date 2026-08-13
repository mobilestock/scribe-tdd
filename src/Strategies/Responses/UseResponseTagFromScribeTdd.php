<?php

namespace AjCastro\ScribeTdd\Strategies\Responses;

use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag;

class UseResponseTagFromScribeTdd extends UseResponseTag
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $testResult = RouteTestResult::getTestResultForRoute($endpointData->route);

        if (empty($testResult)) {
            return [];
        }

        [
            'method' => $methodDocBlock,
        ]
        = RouteTestResult::getTestDocBlocks($endpointData->route, $testResult);

        return $this->getDocBlockResponses($methodDocBlock->getTags());
    }
}
