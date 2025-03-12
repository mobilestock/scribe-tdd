<?php

namespace AjCastro\ScribeTdd\Strategies\Metadata;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Config;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use ReflectionMethod;

class GetFromRoute extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): array
    {
        $routePrefix = array_values(array_filter(explode('/', $endpointData->route->action['prefix'])));

        $middlewares = $endpointData->route->middleware();
        $isAuthenticated = false;

        foreach ($middlewares as $middleware) {
            if (str_starts_with($middleware, Authenticate::class)) {
                $isAuthenticated = true;
                break;
            }
        }

        $isProduction = str_contains(Config::get('app.url'), 'https');
        if ($isProduction) {
            $title = last(explode('/', $endpointData->uri));
        } else {
            /** @var ReflectionMethod $method */
            $method = $endpointData->method;
            $className = $method->class;
            $methodName = $method->name;
            $rootDir = env('ROOT_DIR');
            $fileName = $method->getFileName();
            $startLine = $method->getStartLine();

            $title = "[$className::$methodName](file://$rootDir/apps$fileName#L$startLine)";
        }

        $metadata = [
            'groupName' => $routePrefix[0] ?? '',
            'groupDescription' => '',
            'subgroup' => $routePrefix[1] ?? '',
            'subgroupDescription' => '',
            'title' => $title,
            'description' => '',
            'authenticated' => $isAuthenticated,
        ];

        return $metadata;
    }
}
