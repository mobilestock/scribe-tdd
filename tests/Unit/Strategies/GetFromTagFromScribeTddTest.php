<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Tests\Fixtures\FakeTestController;

function makeDocblockTestEndpoint(string $uri, array $httpMethods, string $action): array
{
    $route = new Route($httpMethods, $uri, ['uses' => FakeTestController::class . "@$action"]);
    $route->bind(Request::create('/' . str_replace('{id}', '1', $uri)));

    setupTestResultForRoute($route, FakeTestController::class, $action);

    $endpoint = makeEndpointData([
        'route' => $route,
        'uri' => $uri,
        'httpMethods' => $httpMethods,
        'method' => new ReflectionMethod(FakeTestController::class, $action),
    ]);

    return [$route, $endpoint];
}

function makeOrphanEndpoint(
    string $uri = 'no-result',
    array $httpMethods = ['GET']
): Knuckles\Camel\Extraction\ExtractedEndpointData {
    $route = new Route($httpMethods, $uri, fn() => null);

    $endpoint = makeEndpointData([
        'route' => $route,
        'uri' => $uri,
        'httpMethods' => $httpMethods,
        'method' => new ReflectionMethod(
            new class {
                public function handle()
                {
                }
            },
            'handle'
        ),
    ]);

    return $endpoint;
}

dataset('tagStrategies', [
    'GetFromBodyParamTagFromScribeTdd' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromBodyParamTagFromScribeTdd::class,
            'orphanUri' => 'no-result',
            'orphanMethods' => ['POST'],
            'docblockUri' => 'items',
            'docblockMethods' => ['POST'],
            'action' => 'store',
            'expectKey' => 'title',
            'expectValues' => ['title.type' => 'string', 'title.required' => true],
        ],
    ],
    'GetFromQueryParamTagFromScribeTdd' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromQueryParamTagFromScribeTdd::class,
            'orphanUri' => 'no-result',
            'orphanMethods' => ['GET'],
            'docblockUri' => 'items/{id}',
            'docblockMethods' => ['GET'],
            'action' => 'show',
            'expectKey' => 'page',
        ],
    ],
    'GetFromUrlParamTagFromScribeTdd' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\UrlParameters\GetFromUrlParamTagFromScribeTdd::class,
            'orphanUri' => 'items/{id}',
            'orphanMethods' => ['GET'],
            'docblockUri' => 'items/{id}',
            'docblockMethods' => ['GET'],
            'action' => 'show',
            'expectKey' => 'id',
            'expectValues' => ['id.type' => 'integer'],
        ],
    ],
    'GetFromHeaderTagFromScribeTdd' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\Headers\GetFromHeaderTagFromScribeTdd::class,
            'orphanUri' => 'no-result',
            'orphanMethods' => ['GET'],
            'docblockUri' => 'items/{id}',
            'docblockMethods' => ['GET'],
            'action' => 'show',
            'expectKey' => 'X-Custom-Header',
        ],
    ],
    'GetFromDocBlocksFromScribeTdd' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\Metadata\GetFromDocBlocksFromScribeTdd::class,
            'orphanUri' => 'no-result',
            'orphanMethods' => ['GET'],
            'docblockUri' => 'items/{id}',
            'docblockMethods' => ['GET'],
            'action' => 'show',
            'expectKey' => 'groupName',
            'expectValues' => ['groupName' => 'Items'],
        ],
    ],
    'GetFromResponseFieldTagFromScribeTdd' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\ResponseFields\GetFromResponseFieldTagFromScribeTdd::class,
            'orphanUri' => 'no-result',
            'orphanMethods' => ['GET'],
            'docblockUri' => 'items/{id}',
            'docblockMethods' => ['GET'],
            'action' => 'show',
            'expectKey' => 'id',
        ],
    ],
]);

it('returns empty array when no test result exists', function (array $data) {
    resetRouteTestResultCache();
    $strategy = new ($data['class'])(new DocumentationConfig(config('scribe') ?? []));

    $result = $strategy->__invoke(makeOrphanEndpoint($data['orphanUri'], $data['orphanMethods']));

    expect($result)->toBe([]);
})->with('tagStrategies');

it('extracts data from docblock when test result exists', function (array $data) {
    resetRouteTestResultCache();
    $strategy = new ($data['class'])(new DocumentationConfig(config('scribe') ?? []));

    [$route, $endpoint] = makeDocblockTestEndpoint($data['docblockUri'], $data['docblockMethods'], $data['action']);

    $result = $strategy->__invoke($endpoint);

    expect($result)->toHaveKey($data['expectKey']);
    foreach ($data['expectValues'] ?? [] as $path => $value) {
        expect(data_get($result, $path))->toBe($value);
    }

    cleanupTestResultForRoute($route);
})->with('tagStrategies');
