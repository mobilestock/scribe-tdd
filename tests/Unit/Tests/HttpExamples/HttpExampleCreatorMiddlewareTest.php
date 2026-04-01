<?php

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use AjCastro\ScribeTdd\Tests\HttpExamples\HttpExampleCreatorMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;

beforeEach(function () {
    ExampleCreator::flushInstances();
    ExampleCreator::$currentInstance = null;
});

it('captures request and response through middleware', function () {
    $route = new Route(['POST'], 'middleware-test', fn () => null);
    $route->bind(Request::create('/middleware-test'));

    $request = Request::create('/middleware-test', 'POST', ['name' => 'test']);
    $request->setRouteResolver(fn () => $route);

    $test = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
    $instance = new ExampleCreator([
        'test' => $test,
        'testMethod' => 'test_method',
        'dataName' => '',
        'providedData' => [],
        'description' => 'test',
    ]);
    ExampleCreator::setCurrentInstance($instance);

    $middleware = new HttpExampleCreatorMiddleware();
    $response = $middleware->handle($request, function ($req) {
        return new Response('{"ok":true}', 200);
    });

    expect($response->getContent())->toBe('{"ok":true}');

    $instances = ExampleCreator::getInstances();
    $routeKey = ExampleCreator::normalizeUriForInstanceKey($route);
    expect($instances[$routeKey])->toHaveCount(1);

    $exampleCreator = $instances[$routeKey][0];
    $prop = new ReflectionProperty($exampleCreator, 'exampleRequests');
    expect($prop->getValue($exampleCreator))->toHaveCount(1);
});

it('preserves original body params even if controller modifies request', function () {
    $route = new Route(['POST'], 'modify-test', fn () => null);
    $route->bind(Request::create('/modify-test'));

    $request = Request::create('/modify-test', 'POST', ['name' => 'original']);
    $request->setRouteResolver(fn () => $route);

    $test = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
    $instance = new ExampleCreator([
        'test' => $test,
        'testMethod' => 'test_modify',
        'dataName' => '',
        'providedData' => [],
        'description' => 'test',
    ]);
    ExampleCreator::setCurrentInstance($instance);

    $middleware = new HttpExampleCreatorMiddleware();
    $middleware->handle($request, function ($req) {
        // Simulate controller modifying request
        $req->request->set('extra', 'injected');
        return new Response('', 200);
    });

    // After middleware, original params should be restored
    expect($request->request->all())->toBe(['name' => 'original']);
    expect($request->request->has('extra'))->toBeFalse();
});

it('preserves original query params even if controller modifies request', function () {
    $route = new Route(['GET'], 'query-modify-test', fn () => null);
    $route->bind(Request::create('/query-modify-test'));

    $request = Request::create('/query-modify-test?status=active', 'GET');
    $request->setRouteResolver(fn () => $route);

    $test = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
    $instance = new ExampleCreator([
        'test' => $test,
        'testMethod' => 'test_query',
        'dataName' => '',
        'providedData' => [],
        'description' => 'test',
    ]);
    ExampleCreator::setCurrentInstance($instance);

    $middleware = new HttpExampleCreatorMiddleware();
    $middleware->handle($request, function ($req) {
        $req->query->set('injected', 'true');
        return new Response('', 200);
    });

    expect($request->query->all())->toBe(['status' => 'active']);
});
