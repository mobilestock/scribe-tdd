<?php

use AjCastro\ScribeTdd\Strategies\QueryParameters\AddPaginationParametersFromScribeTdd;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

beforeEach(function () {
    resetRouteTestResultCache();

    $this->strategy = new AddPaginationParametersFromScribeTdd(new DocumentationConfig(config('scribe') ?? []));
});

it('returns empty array when no test result exists', function () {
    $route = new Route(['GET'], 'no-result', fn() => null);
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

it('returns pagination params when usesPagination tag present', function () {
    $route = new Route(['GET'], 'items/{id}', ['uses' => 'Tests\Fixtures\FakeTestController@show']);
    $route->bind(Request::create('/items/1'));

    setupTestResultForRoute($route, 'Tests\Fixtures\FakeTestController', 'show');

    $method = new ReflectionMethod(Tests\Fixtures\FakeTestController::class, 'show');

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

    expect($result)->toHaveKey('page');
    expect($result)->toHaveKey('per_page');
    expect($result['page']['example'])->toBe(1);

    cleanupTestResultForRoute($route);
});

it('returns empty when no usesPagination tag', function () {
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

    expect($result)->toBe([]);

    cleanupTestResultForRoute($route);
});
