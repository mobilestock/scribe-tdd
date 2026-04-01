<?php

use AjCastro\ScribeTdd\Strategies\UrlParameters\GetFromTestResult;
use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use AjCastro\ScribeTdd\Tests\ExampleCreator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Knuckles\Scribe\Tools\DocumentationConfig;

beforeEach(function () {
    $this->strategy = new GetFromTestResult(new DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionClass(RouteTestResult::class);
    $property = $reflection->getProperty('cache');
    $property->setAccessible(true);
    $property->setValue(null, []);
});

function makeEndpointForUrlTest(string $uri, $route)
{
    $controller = new class {
        public function handle()
        {
            return 'ok';
        }
    };

    return makeEndpointData([
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

it('returns url params from test result', function () {
    $route = new Route(['GET'], 'url-test/{id}', fn() => null);
    $dir = ExampleCreator::writeDir($route);
    File::makeDirectory($dir, 0755, true, true);
    File::put(
        $dir . '/data.json',
        json_encode([
            'url_params' => ['id' => ['type' => 'integer', 'example' => 42]],
        ])
    );

    $endpoint = makeEndpointForUrlTest('url-test/{id}', $route);
    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toHaveKey('id');
    expect($result['id']['example'])->toBe(42);

    File::deleteDirectory($dir);
});

it('returns empty array when no test result exists', function () {
    $route = new Route(['GET'], 'no-url-test/{id}', fn() => null);
    $endpoint = makeEndpointForUrlTest('no-url-test/{id}', $route);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toBe([]);
});
