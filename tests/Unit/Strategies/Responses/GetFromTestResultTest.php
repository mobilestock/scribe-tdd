<?php

use AjCastro\ScribeTdd\Strategies\Responses\GetFromTestResult;
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

function makeEndpointForResponseTest(string $uri, $route): ExtractedEndpointData
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

it('returns responses from test result', function () {
    $route = new Illuminate\Routing\Route(['GET'], 'resp-test', fn() => null);
    $dir = AjCastro\ScribeTdd\Tests\ExampleCreator::writeDir($route);
    File::makeDirectory($dir, 0755, true, true);
    File::put(
        $dir . '/data.json',
        json_encode([
            'responses' => [['status' => 200, 'content' => '{"ok":true}']],
        ])
    );

    $endpoint = makeEndpointForResponseTest('resp-test', $route);
    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toHaveCount(1);
    expect($result[0]['status'])->toBe(200);

    File::deleteDirectory($dir);
});

it('returns empty array when no test result exists', function () {
    $route = new Illuminate\Routing\Route(['GET'], 'no-resp-test', fn() => null);
    $endpoint = makeEndpointForResponseTest('no-resp-test', $route);

    $result = $this->strategy->__invoke($endpoint);

    expect($result)->toBe([]);
});
