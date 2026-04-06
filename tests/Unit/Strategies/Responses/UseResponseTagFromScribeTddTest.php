<?php

use AjCastro\ScribeTdd\Strategies\Responses\UseResponseTagFromScribeTdd;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Tests\Fixtures\FakeTestController;

beforeEach(function () {
    resetRouteTestResultCache();

    $documentationConfig = new DocumentationConfig(config('scribe') ?? []);
    $this->strategy = new UseResponseTagFromScribeTdd($documentationConfig);
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

it('extracts response tag from docblock when test result exists', function () {
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

    expect($result)->toBeArray();
    expect($result)->not->toBeEmpty();
    expect($result[0]['status'])->toBe(200);

    cleanupTestResultForRoute($route);
});
