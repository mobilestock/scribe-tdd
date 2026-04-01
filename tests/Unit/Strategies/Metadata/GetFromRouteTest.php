<?php

use AjCastro\ScribeTdd\Strategies\Metadata\GetFromRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;

beforeEach(function () {
    $this->strategy = new GetFromRoute(new DocumentationConfig(config('scribe') ?? []));
});

function makeEndpoint(string $uri, array $httpMethods, $route)
{
    $controller = new class {
        public function handle() { return 'ok'; }
    };
    $method = new ReflectionMethod($controller, 'handle');

    return makeEndpointData([
        'route' => $route,
        'uri' => $uri,
        'httpMethods' => $httpMethods,
        'method' => $method,
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ]);
}

function makeClosureEndpoint(string $uri, array $httpMethods, $route, Closure $closure)
{
    return makeEndpointData([
        'route' => $route,
        'uri' => $uri,
        'httpMethods' => $httpMethods,
        'method' => new \ReflectionFunction($closure),
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ]);
}

function findRoute(string $method, string $uri)
{
    return Route::getRoutes()->match(
        \Illuminate\Http\Request::create($uri, $method)
    );
}

it('uses route prefix as group name', function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', fn () => 'ok');
    });

    $route = findRoute('GET', '/orders');
    $endpointData = makeEndpoint('orders', ['GET'], $route);

    $result = $this->strategy->__invoke($endpointData);

    expect($result['groupName'])->toBe('orders');
});

it('falls back to first URI segment when no prefix', function () {
    Route::get('/my-test-route', fn () => 'ok');

    $route = findRoute('GET', '/my-test-route');
    $endpointData = makeEndpoint('my-test-route', ['GET'], $route);

    $result = $this->strategy->__invoke($endpointData);

    expect($result['groupName'])->toBe('my-test-route');
});

it('detects authenticated routes with auth middleware', function () {
    Route::middleware(\Illuminate\Auth\Middleware\Authenticate::class)->get('/auth-test', fn () => 'ok');

    $route = findRoute('GET', '/auth-test');
    $endpointData = makeEndpoint('auth-test', ['GET'], $route);

    $result = $this->strategy->__invoke($endpointData);

    expect($result['authenticated'])->toBeTrue();
});

it('marks unauthenticated routes correctly', function () {
    Route::get('/no-auth-test', fn () => 'ok');

    $route = findRoute('GET', '/no-auth-test');
    $endpointData = makeEndpoint('no-auth-test', ['GET'], $route);

    $result = $this->strategy->__invoke($endpointData);

    expect($result['authenticated'])->toBeFalse();
});

it('generates title from URI in production', function () {
    Config::set('app.url', 'https://api.example.com');

    Route::get('/users/my-list', fn () => 'ok');

    $route = findRoute('GET', '/users/my-list');
    $endpointData = makeEndpoint('users/my-list', ['GET'], $route);

    $result = $this->strategy->__invoke($endpointData);

    expect($result['title'])->toBe('my-list');
});

it('uses second prefix segment as subgroup', function () {
    Route::prefix('api/v2')->group(function () {
        Route::get('/my-items', fn () => 'ok');
    });

    $route = findRoute('GET', '/api/v2/my-items');
    $endpointData = makeEndpoint('api/v2/my-items', ['GET'], $route);

    $result = $this->strategy->__invoke($endpointData);

    expect($result['groupName'])->toBe('api');
    expect($result['subgroup'])->toBe('v2');
});

it('generates file link for closure routes pointing to route file', function () {
    Config::set('app.url', 'http://localhost');
    putenv('ROOT_DIR=/test/root');

    $closure = function () { return 'ok'; };
    Route::get('/closure-link-test', $closure);

    $route = findRoute('GET', '/closure-link-test');
    $endpointData = makeClosureEndpoint('closure-link-test', ['GET'], $route, $closure);

    $result = $this->strategy->__invoke($endpointData);

    $reflection = new \ReflectionFunction($closure);
    $expectedFile = $reflection->getFileName();
    $expectedLine = $reflection->getStartLine();
    $expectedBaseName = basename($expectedFile);

    expect($result['title'])->toBe("[$expectedBaseName:$expectedLine](file:///test/root/apps$expectedFile#L$expectedLine)");

    putenv('ROOT_DIR');
});

it('returns all expected metadata keys', function () {
    Route::get('/meta-test', fn () => 'ok');

    $route = findRoute('GET', '/meta-test');
    $endpointData = makeEndpoint('meta-test', ['GET'], $route);

    $result = $this->strategy->__invoke($endpointData);

    expect($result)->toHaveKeys([
        'groupName',
        'groupDescription',
        'subgroup',
        'subgroupDescription',
        'title',
        'description',
        'authenticated',
    ]);
});
