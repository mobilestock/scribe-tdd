<?php

use AjCastro\ScribeTdd\Strategies\UrlParameters\GetFromUrlParamTagFromScribeTdd;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Tests\Fixtures\FakeTestController;

beforeEach(function () {
    resetRouteTestResultCache();

    $this->strategy = new GetFromUrlParamTagFromScribeTdd(new DocumentationConfig(config('scribe') ?? []));
});

it('returns empty array when no test result exists', function () {
    $route = new Route(['GET'], 'items/{id}', fn() => null);
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
        'uri' => 'items/{id}',
        'httpMethods' => ['GET'],
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

it('extracts url params from docblock when test result exists', function () {
    $route = new Route(['GET'], 'items/{id}', ['uses' => 'Tests\Fixtures\FakeTestController@show']);
    $route->bind(Request::create('/items/1'));

    setupTestResultForRoute($route, 'Tests\Fixtures\FakeTestController', 'show');

    $method = new ReflectionMethod(FakeTestController::class, 'show');

    $endpoint = new ExtractedEndpointData([
        'route' => $route,
        'uri' => 'items/{id}',
        'httpMethods' => ['GET'],
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

    expect($result)->toHaveKey('id');
    expect($result['id']['type'])->toBe('integer');

    cleanupTestResultForRoute($route);
});
