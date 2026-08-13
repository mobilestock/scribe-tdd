<?php

namespace AjCastro\ScribeTdd\Strategies\Metadata;

use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks;

class GetFromDocBlocksFromScribeTdd extends GetFromDocBlocks
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): array
    {
        $testResult = RouteTestResult::getTestResultForRoute($endpointData->route);

        if (empty($testResult)) {
            return [];
        }

        [
            'method' => $methodDocBlock,
            'class' => $classDocBlock,
        ]
        = RouteTestResult::getTestDocBlocks($endpointData->route, $testResult);

        return $this->getMetadataFromDocBlock($methodDocBlock, $classDocBlock);
    }
}
