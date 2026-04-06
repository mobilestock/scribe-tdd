<?php

use AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromBodyParamTagFromScribeTdd;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Tests\Fixtures\FakeTestController;

beforeEach(function () {
    resetRouteTestResultCache();

    $this->endpointData = [
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ];

    $documentationConfig = new DocumentationConfig(config('scribe') ?? []);
    $this->strategy = new GetFromBodyParamTagFromScribeTdd($documentationConfig);
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

    $endpoint = makeEndpointData(
        array_merge($this->endpointData, [
            'route' => $route,
            'uri' => 'no-result',
            'httpMethods' => ['POST'],
            'method' => $method,
        ])
    );

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toBe([]);
});

it('extracts body params from docblock when test result exists', function () {
    $route = new Route(['POST'], 'items', ['uses' => FakeTestController::class . '@store']);
    $route->bind(Request::create('/items'));

    setupTestResultForRoute($route, FakeTestController::class, 'store');

    $method = new ReflectionMethod(FakeTestController::class, 'store');

    $endpoint = makeEndpointData(
        array_merge($this->endpointData, [
            'route' => $route,
            'uri' => 'items',
            'httpMethods' => ['POST'],
            'method' => $method,
        ])
    );

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toHaveKey('title');
    expect($result['title']['type'])->toBe('string');
    expect($result['title']['required'])->toBeTrue();

    cleanupTestResultForRoute($route);
});
