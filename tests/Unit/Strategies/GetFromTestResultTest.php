<?php

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

function makeTestResultEndpoint(string $uri, Route $route, array $httpMethods = ['GET']): ExtractedEndpointData
{
    $controller = new class {
        public function handle()
        {
            return 'ok';
        }
    };

    $endpoint = makeEndpointData([
        'route' => $route,
        'uri' => $uri,
        'httpMethods' => $httpMethods,
        'method' => new ReflectionMethod($controller, 'handle'),
    ]);

    return $endpoint;
}

dataset('testResultStrategies', [
    'BodyParameters' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromTestResult::class,
            'httpMethods' => ['POST'],
            'uri' => 'body-test',
            'jsonData' => ['body_params' => ['name' => ['type' => 'string', 'example' => 'John']]],
            'expectKey' => 'name',
            'expectValues' => ['name.example' => 'John'],
        ],
    ],
    'QueryParameters' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromTestResult::class,
            'httpMethods' => ['GET'],
            'uri' => 'query-test',
            'jsonData' => ['query_params' => ['page' => ['type' => 'integer', 'example' => 1]]],
            'expectKey' => 'page',
            'expectValues' => ['page.example' => 1],
        ],
    ],
    'Responses' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\Responses\GetFromTestResult::class,
            'httpMethods' => ['GET'],
            'uri' => 'resp-test',
            'jsonData' => ['responses' => [['status' => 200, 'content' => '{"ok":true}']]],
            'expectCount' => 1,
            'expectValues' => ['0.status' => 200],
        ],
    ],
    'UrlParameters' => [
        [
            'class' => AjCastro\ScribeTdd\Strategies\UrlParameters\GetFromTestResult::class,
            'httpMethods' => ['GET'],
            'uri' => 'url-test/{id}',
            'jsonData' => ['url_params' => ['id' => ['type' => 'integer', 'example' => 42]]],
            'expectKey' => 'id',
            'expectValues' => ['id.example' => 42],
        ],
    ],
]);

it('returns params from test result', function (array $data) {
    resetRouteTestResultCache();
    $strategy = new ($data['class'])(new DocumentationConfig(config('scribe') ?? []));

    $route = new Route($data['httpMethods'], $data['uri'], fn() => null);
    $dir = ExampleCreator::writeDir($route);
    File::makeDirectory($dir, 0755, true, true);
    File::put($dir . '/data.json', json_encode($data['jsonData']));

    $result = $strategy->__invoke(makeTestResultEndpoint($data['uri'], $route, $data['httpMethods']));

    if (isset($data['expectKey'])) {
        expect($result)->toHaveKey($data['expectKey']);
    }
    if (isset($data['expectCount'])) {
        expect($result)->toHaveCount($data['expectCount']);
    }
    foreach ($data['expectValues'] ?? [] as $path => $value) {
        expect(data_get($result, $path))->toBe($value);
    }

    File::deleteDirectory($dir);
})->with('testResultStrategies');

it('returns empty array when no test result exists', function (array $data) {
    resetRouteTestResultCache();
    $strategy = new ($data['class'])(new DocumentationConfig(config('scribe') ?? []));

    $route = new Route($data['httpMethods'], 'no-' . $data['uri'], fn() => null);

    $result = $strategy->__invoke(makeTestResultEndpoint('no-' . $data['uri'], $route, $data['httpMethods']));

    expect($result)->toBe([]);
})->with('testResultStrategies');
