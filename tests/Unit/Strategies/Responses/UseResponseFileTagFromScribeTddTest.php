<?php

use AjCastro\ScribeTdd\Strategies\Responses\UseResponseFileTagFromScribeTdd;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;

beforeEach(function () {
    resetRouteTestResultCache();

    $this->strategy = new UseResponseFileTagFromScribeTdd(new DocumentationConfig(config('scribe') ?? []));
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

it('returns empty when no responseFile tag in docblock', function () {
    $route = new Route(['GET'], 'items/{id}', ['uses' => 'Tests\Fixtures\FakeTestController@show']);
    $route->bind(Request::create('/items/1'));

    setupTestResultForRoute($route, 'Tests\Fixtures\FakeTestController', 'show');

    $method = new ReflectionMethod(Tests\Fixtures\FakeTestController::class, 'show');

    $endpoint = makeEndpointData([
        'route' => $route,
        'uri' => 'items/{id}',
        'httpMethods' => ['GET'],
        'method' => $method,
    ]);

    $result = $this->strategy->__invoke($endpoint);

    // FakeTestController@show has @response but not @responseFile, so empty
    expect($result)->toBeEmpty();

    cleanupTestResultForRoute($route);
});
