<?php

use AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromBodyParamTagFromScribeTdd;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

beforeEach(function () {
    resetRouteTestResultCache();

    $this->strategy = new GetFromBodyParamTagFromScribeTdd(new DocumentationConfig(config('scribe') ?? []));
});

it('returns empty array when no test result exists', function () {
    $route = new Route(['POST'], 'no-result', fn() => null);
    $method = new ReflectionMethod(
        new class {
            public function handle()
            {
            }
        },
        'handle'
    );

    $endpoint = new ExtractedEndpointData([
        'route' => $route,
        'uri' => 'no-result',
        'httpMethods' => ['POST'],
        'method' => $method,
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ]);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toBe([]);
});

it('extracts body params from docblock when test result exists', function () {
    $route = new Route(['POST'], 'items', ['uses' => 'Tests\Fixtures\FakeTestController@store']);
    $route->bind(Request::create('/items'));

    setupTestResultForRoute($route, 'Tests\Fixtures\FakeTestController', 'store');

    $method = new ReflectionMethod(Tests\Fixtures\FakeTestController::class, 'store');

    $endpoint = new ExtractedEndpointData([
        'route' => $route,
        'uri' => 'items',
        'httpMethods' => ['POST'],
        'method' => $method,
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ]);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toHaveKey('title');
    expect($result['title']['type'])->toBe('string');
    expect($result['title']['required'])->toBeTrue();

    cleanupTestResultForRoute($route);
});
