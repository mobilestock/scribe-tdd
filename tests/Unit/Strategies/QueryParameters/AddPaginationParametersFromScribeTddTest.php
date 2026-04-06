<?php

use AjCastro\ScribeTdd\Strategies\QueryParameters\AddPaginationParametersFromScribeTdd;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Tests\Fixtures\FakeTestController;

beforeEach(function () {
    resetRouteTestResultCache();

    $documentationConfig = new DocumentationConfig(config('scribe') ?? []);
    $this->strategy = new AddPaginationParametersFromScribeTdd($documentationConfig);
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

    $endpoint = makeEndpointData([
        'route' => $route,
        'uri' => 'no-result',
        'httpMethods' => ['GET'],
        'method' => $method,
    ]);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toBe([]);
});

it('returns pagination params when usesPagination tag present', function () {
    $route = new Route(['GET'], 'items/{id}', ['uses' => FakeTestController::class . '@show']);
    $route->bind(Request::create('/items/1'));

    setupTestResultForRoute($route, FakeTestController::class, 'show');

    $method = new ReflectionMethod(FakeTestController::class, 'show');

    $endpoint = makeEndpointData([
        'route' => $route,
        'uri' => 'items/{id}',
        'httpMethods' => ['GET'],
        'method' => $method,
    ]);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toHaveKey('page');
    expect($result)->toHaveKey('per_page');
    expect($result['page']['example'])->toBe(1);

    cleanupTestResultForRoute($route);
});

it('returns empty when no usesPagination tag', function () {
    $route = new Route(['POST'], 'items', ['uses' => FakeTestController::class . '@store']);
    $route->bind(Request::create('/items'));

    setupTestResultForRoute($route, FakeTestController::class, 'store');

    $method = new ReflectionMethod(FakeTestController::class, 'store');

    $endpoint = makeEndpointData([
        'route' => $route,
        'uri' => 'items',
        'httpMethods' => ['POST'],
        'method' => $method,
    ]);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toBe([]);

    cleanupTestResultForRoute($route);
});
