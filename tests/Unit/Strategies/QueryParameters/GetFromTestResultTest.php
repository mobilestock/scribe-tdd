<?php

use AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromTestResult;
use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use Illuminate\Support\Facades\File;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

beforeEach(function () {
    $this->strategy = new GetFromTestResult(new DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionClass(RouteTestResult::class);
    $property = $reflection->getProperty('cache');
    $property->setAccessible(true);
    $property->setValue(null, []);
});

function makeEndpointForQueryTest(string $uri, $route): ExtractedEndpointData
{
    $controller = new class {
        public function handle()
        {
            return 'ok';
        }
    };

    return new ExtractedEndpointData([
        'route' => $route,
        'uri' => $uri,
        'httpMethods' => ['GET'],
        'method' => new ReflectionMethod($controller, 'handle'),
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ]);
}

it('returns query params from test result', function () {
    $route = new Illuminate\Routing\Route(['GET'], 'query-test', fn() => null);
    $dir = AjCastro\ScribeTdd\Tests\ExampleCreator::writeDir($route);
    File::makeDirectory($dir, 0755, true, true);
    File::put(
        $dir . '/data.json',
        json_encode([
            'query_params' => ['page' => ['type' => 'integer', 'example' => 1]],
        ])
    );

    $endpoint = makeEndpointForQueryTest('query-test', $route);
    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toHaveKey('page');
    expect($result['page']['example'])->toBe(1);

    File::deleteDirectory($dir);
});

it('returns empty array when no test result exists', function () {
    $route = new Illuminate\Routing\Route(['GET'], 'no-query-test', fn() => null);
    $endpoint = makeEndpointForQueryTest('no-query-test', $route);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toBe([]);
});
