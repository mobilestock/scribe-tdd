<?php

use AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromTestResult;
use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use Illuminate\Support\Facades\File;
use Knuckles\Scribe\Tools\DocumentationConfig;

beforeEach(function () {
    $this->strategy = new GetFromTestResult(new DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionClass(RouteTestResult::class);
    $property = $reflection->getProperty('cache');
    $property->setAccessible(true);
    $property->setValue(null, []);
});

function makeEndpointForBodyTest(string $uri, $route)
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
        'httpMethods' => ['POST'],
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

it('returns body params from test result', function () {
    $route = new Illuminate\Routing\Route(['POST'], 'body-test', fn() => null);
    $dir = AjCastro\ScribeTdd\Tests\ExampleCreator::writeDir($route);
    File::makeDirectory($dir, 0755, true, true);
    File::put(
        $dir . '/data.json',
        json_encode([
            'body_params' => ['name' => ['type' => 'string', 'example' => 'John']],
        ])
    );

    $endpoint = makeEndpointForBodyTest('body-test', $route);
    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toHaveKey('name');
    expect($result['name']['example'])->toBe('John');

    File::deleteDirectory($dir);
});

it('returns empty array when no test result exists', function () {
    $route = new Illuminate\Routing\Route(['POST'], 'no-body-test', fn() => null);
    $endpoint = makeEndpointForBodyTest('no-body-test', $route);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toBe([]);
});
