<?php

namespace AjCastro\ScribeTdd\Strategies\Metadata;

use Illuminate\Auth\Middleware\Authenticate;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

class GetFromRoute extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): array
    {
        $routePrefix = array_values(array_filter(explode('/', $endpointData->route->action['prefix'])));

        $middlewares = $endpointData->route->middleware();
        $isAuthenticated = in_array(Authenticate::class, $middlewares);

        $metadata = [
            'groupName' => $routePrefix[0],
            'groupDescription' => '',
            'subgroup' => $routePrefix[1] ?? '',
            'subgroupDescription' => '',
            'title' => last(explode('/', $endpointData->uri)) ?: '',
            'description' => '',
            'authenticated' => $isAuthenticated,
        ];

        return $metadata;
    }
}
