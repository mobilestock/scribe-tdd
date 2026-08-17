<?php

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use AjCastro\ScribeTdd\Tests\ExampleRequest;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;

enum ExampleRequestStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

beforeEach(function () {
    ExampleCreator::flushInstances();
    ExampleCreator::$currentInstance = null;
});

function makeExampleCreatorForRequest(): ExampleCreator
{
    $test = Mockery::mock(TestCase::class);
    return new ExampleCreator([
        'test' => $test,
        'testMethod' => 'test_method',
        'dataName' => '',
        'providedData' => [],
        'description' => 'test description',
    ]);
}

it('captures url parameters from request', function () {
    $route = new Route(['GET'], 'items/{id}', fn() => null);
    $route->bind(Request::create('/items/42'));
    $route->setParameter('id', 42);

    $request = Request::create('/items/42', 'GET');
    $request->setRouteResolver(fn() => $route);

    $response = new Response('{"ok":true}', 200);
    $creator = makeExampleCreatorForRequest();

    $exampleRequest = new ExampleRequest($request, $response, $creator);
    $urlParams = $exampleRequest->getUrlParams();

    expect($urlParams)->toHaveKey('id');
    expect($urlParams['id']['example'])->toBe(42);
    expect($urlParams['id']['type'])->toBe('integer');
});

it('detects required url parameters', function () {
    $route = new Route(['GET'], 'items/{id}', fn() => null);
    $route->bind(Request::create('/items/1'));
    $route->setParameter('id', 1);

    $request = Request::create('/items/1', 'GET');
    $request->setRouteResolver(fn() => $route);

    $response = new Response('', 200);
    $creator = makeExampleCreatorForRequest();

    $exampleRequest = new ExampleRequest($request, $response, $creator);
    $urlParams = $exampleRequest->getUrlParams();

    expect($urlParams['id']['required'])->toBeTrue();
});

it('detects optional url parameters', function () {
    $route = new Route(['GET'], 'items/{id?}', fn() => null);
    $route->bind(Request::create('/items/1'));
    $route->setParameter('id', 1);

    $request = Request::create('/items/1', 'GET');
    $request->setRouteResolver(fn() => $route);

    $response = new Response('', 200);
    $creator = makeExampleCreatorForRequest();

    $exampleRequest = new ExampleRequest($request, $response, $creator);
    $urlParams = $exampleRequest->getUrlParams();

    expect($urlParams['id']['required'])->toBeFalse();
});

it('captures query parameters', function () {
    $route = new Route(['GET'], 'items', fn() => null);
    $route->bind(Request::create('/items'));

    $request = Request::create('/items?page=2&limit=10', 'GET');
    $request->setRouteResolver(fn() => $route);

    $response = new Response('', 200);
    $creator = makeExampleCreatorForRequest();

    $exampleRequest = new ExampleRequest($request, $response, $creator);
    $queryParams = $exampleRequest->getQueryParams();

    expect($queryParams)->toHaveKey('page');
    expect($queryParams)->toHaveKey('limit');
});

it('captures body parameters', function () {
    $route = new Route(['POST'], 'items', fn() => null);
    $route->bind(Request::create('/items'));

    $request = Request::create('/items', 'POST', ['name' => 'Widget', 'qty' => 5]);
    $request->setRouteResolver(fn() => $route);

    $response = new Response('', 201);
    $creator = makeExampleCreatorForRequest();

    $exampleRequest = new ExampleRequest($request, $response, $creator);
    $bodyParams = $exampleRequest->getBodyParams();

    expect($bodyParams)->toHaveKey('name');
    expect($bodyParams['name']['example'])->toBe('Widget');
});

it('captures response with status, headers, content and description', function () {
    $route = new Route(['POST'], 'items', fn() => null);
    $route->bind(Request::create('/items'));

    $request = Request::create('/items', 'POST');
    $request->setRouteResolver(fn() => $route);

    $response = new Response('{"id":1}', 201, ['Content-Type' => 'application/json']);
    $creator = makeExampleCreatorForRequest();

    $exampleRequest = new ExampleRequest($request, $response, $creator);
    $resp = $exampleRequest->getResponse();

    expect($resp['status'])->toBe(201);
    expect($resp['content'])->toBe('{"id":1}');
    expect($resp['description'])->toContain('201');
    expect($resp['description'])->toContain('test description');
});

it('captures enum origins from JSON response content', function () {
    $route = new Route(['GET'], 'items', fn() => null);
    $route->bind(Request::create('/items'));
    $request = Request::create('/items');
    $request->setRouteResolver(fn() => $route);
    $response = new JsonResponse([
        'statuses' => [ExampleRequestStatus::ACTIVE, ExampleRequestStatus::INACTIVE],
        'plain' => ['active', 'inactive'],
    ]);

    $capturedResponse = (new ExampleRequest($request, $response, makeExampleCreatorForRequest()))->getResponse();

    expect($capturedResponse['content_enum_paths'])->toBe([
        ['path' => ['statuses', 0], 'class' => ExampleRequestStatus::class],
        ['path' => ['statuses', 1], 'class' => ExampleRequestStatus::class],
    ]);
});

it('converts eloquent model url param to its key', function () {
    $model = new class {
        public function getKey()
        {
            return 99;
        }
    };

    $route = new Route(['GET'], 'items/{item}', fn() => null);
    $route->bind(Request::create('/items/99'));
    $route->setParameter('item', $model);

    $request = Request::create('/items/99', 'GET');
    $request->setRouteResolver(fn() => $route);

    $response = new Response('', 200);
    $creator = makeExampleCreatorForRequest();

    $exampleRequest = new ExampleRequest($request, $response, $creator);
    $urlParams = $exampleRequest->getUrlParams();

    expect($urlParams['item']['example'])->toBe(99);
});

it('generates unique id', function () {
    $route = new Route(['GET'], 'test', fn() => null);
    $route->bind(Request::create('/test'));

    $request = Request::create('/test');
    $request->setRouteResolver(fn() => $route);

    $response = new Response('', 200);
    $creator = makeExampleCreatorForRequest();

    $req1 = new ExampleRequest($request, $response, $creator);
    $req2 = new ExampleRequest($request, $response, $creator);

    expect($req1->id)->not->toBe($req2->id);
});
