<?php

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Extraction\ResponseCollection;

function setupTestResultForRoute(Route $route, string $testClass, string $testMethod): void
{
    $dir = ExampleCreator::writeDir($route);
    File::makeDirectory($dir, 0755, true, true);

    $data = [
        'test_class' => $testClass,
        'test_method' => $testMethod,
        'url_params' => [],
        'query_params' => [],
        'body_params' => [],
        'responses' => [['status' => 200, 'content' => '{"ok":true}', 'description' => '200, test']],
    ];

    File::put($dir . '/test-result.json', json_encode($data, JSON_PRETTY_PRINT));
}

function cleanupTestResultForRoute(Route $route): void
{
    $dir = ExampleCreator::writeDir($route);
    if (File::isDirectory($dir)) {
        File::deleteDirectory($dir);
    }
}

function resetRouteTestResultCache(): void
{
    $reflection = new ReflectionClass(RouteTestResult::class);
    $property = $reflection->getProperty('cache');
    $property->setValue(null, []);
}

function makeEndpointData(array $params): ExtractedEndpointData
{
    $base = [
        'route' => null,
        'uri' => '',
        'httpMethods' => [],
        'method' => null,
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ];
    $params = array_merge($base, $params);
    $params['metadata'] = new Metadata($params['metadata'] ?? []);
    $params['responses'] = new ResponseCollection($params['responses'] ?? []);

    return new ExtractedEndpointData($params);
}

function makeOutputEndpointData(array $params): Knuckles\Camel\Output\OutputEndpointData
{
    $params['metadata'] = new Metadata($params['metadata'] ?? []);

    if (is_array($params['responses'] ?? null)) {
        $responses = [];
        foreach ($params['responses'] as $r) {
            $responses[] = new Knuckles\Camel\Extraction\Response($r);
        }
        $params['responses'] = new ResponseCollection($responses);
    } else {
        $params['responses'] = new ResponseCollection();
    }

    return new Knuckles\Camel\Output\OutputEndpointData($params);
}
