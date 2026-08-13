<?php

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use AjCastro\ScribeTdd\Tests\ExampleRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;

beforeEach(function () {
    ExampleCreator::flushInstances();
    ExampleCreator::$currentInstance = null;
});

describe('normalizeUriForInstanceKey', function () {
    it('replaces slashes with tildes and includes methods', function () {
        $route = new Route(['GET', 'HEAD'], 'orders/{id}', fn() => null);

        $key = ExampleCreator::normalizeUriForInstanceKey($route);

        expect($key)->toBe('orders~{id},GET,HEAD');
    });

    it('replaces question marks with dots', function () {
        $route = new Route(['GET', 'HEAD'], 'users/{user?}', fn() => null);

        $key = ExampleCreator::normalizeUriForInstanceKey($route);

        expect($key)->toContain('{user.}');
    });
});

describe('writeDir', function () {
    it('returns storage path with normalized route key', function () {
        $route = new Route(['POST'], 'payments/process', fn() => null);

        $dir = ExampleCreator::writeDir($route);

        expect($dir)->toBe(config('scribe-tdd.artifact_directory') . '/payments~process,POST');
    });
});

describe('getInstanceForRoute', function () {
    it('creates new instance per test for same route', function () {
        $route = new Route(['POST'], 'orders', fn() => null);

        // Simulate first test
        $test1 = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance1 = new ExampleCreator([
            'test' => $test1,
            'testMethod' => 'test_create_order',
            'dataName' => '',
            'providedData' => [],
            'description' => 'creates an order',
        ]);
        ExampleCreator::setCurrentInstance($instance1);
        $result1 = ExampleCreator::getInstanceForRoute($route);

        // Simulate second test
        $test2 = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance2 = new ExampleCreator([
            'test' => $test2,
            'testMethod' => 'test_create_another_order',
            'dataName' => '',
            'providedData' => [],
            'description' => 'creates another order',
        ]);
        ExampleCreator::setCurrentInstance($instance2);
        $result2 = ExampleCreator::getInstanceForRoute($route);

        expect($result1->id)->not->toBe($result2->id);

        $instances = ExampleCreator::getInstances();
        $routeKey = ExampleCreator::normalizeUriForInstanceKey($route);
        expect($instances[$routeKey])->toHaveCount(2);
    });

    it('returns existing instance for same test and route', function () {
        $route = new Route(['POST'], 'items', fn() => null);

        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_create',
            'dataName' => '',
            'providedData' => [],
            'description' => 'test',
        ]);
        ExampleCreator::setCurrentInstance($instance);

        $result1 = ExampleCreator::getInstanceForRoute($route);
        $result2 = ExampleCreator::getInstanceForRoute($route);

        expect($result1)->toBe($result2);
    });
});

describe('flushInstances', function () {
    it('clears all registered instances', function () {
        $route = new Route(['GET'], 'test', fn() => null);

        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_method',
            'dataName' => '',
            'providedData' => [],
            'description' => 'test',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        expect(ExampleCreator::getInstances())->not->toBeEmpty();

        ExampleCreator::flushInstances();

        expect(ExampleCreator::getInstances())->toBeEmpty();
    });
});

describe('docblocks', function () {
    it('captures the test method docblock for later generation', function () {
        $test = new class {
            /** @urlParam id integer required The item ID. */
            public function test_example(): void
            {
            }
        };

        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_example',
            'dataName' => '',
            'providedData' => [],
            'description' => 'test',
        ]);

        expect($instance->testMethodDocBlock)->toContain('@urlParam id');
    });
});

describe('makeId', function () {
    it('creates id from class, method and dataName', function () {
        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_example',
            'dataName' => 'dataset1',
            'providedData' => [],
            'description' => 'test',
        ]);

        expect($instance->id)->toContain('test_example');
        expect($instance->id)->toContain('dataset1');
        expect($instance->id)->toContain('--');
    });

    it('omits empty dataName from id', function () {
        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_example',
            'dataName' => '',
            'providedData' => [],
            'description' => 'test',
        ]);

        expect($instance->id)->not->toContain('--dataset');
    });

    it('replaces path separators in nested dataset names', function (string $dataName) {
        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_example',
            'dataName' => $dataName,
            'providedData' => [],
            'description' => 'test',
        ]);

        $id = $instance->id;

        expect($id)->not->toContain('/')->not->toContain('\\');
    })->with([
        'forward slash' => 'dataset "with" / dataset "first_mile_pending"',
        'backslash' => 'App\\Enum\\OrderItemStatus',
    ]);
});

describe('writePath', function () {
    it('returns path with route key and instance id', function () {
        $route = new Route(['POST'], 'orders', fn() => null);

        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_create',
            'dataName' => '',
            'providedData' => [],
            'description' => 'test',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        $path = $instance->writePath();

        expect($path)->toContain('scribe-tdd/');
        expect($path)->toContain($instance->id . '.json');
    });
});

describe('toArray', function () {
    it('returns array with extra data and merged params', function () {
        $route = new Route(['POST'], 'items', fn() => null);
        $route->bind(Request::create('/items'));

        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_create',
            'dataName' => '',
            'providedData' => [],
            'description' => 'creates item',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        $request = Request::create('/items', 'POST', ['name' => 'Widget']);
        $request->setRouteResolver(fn() => $route);
        $response = new Response('{"id":1}', 201, ['Content-Type' => 'application/json']);

        $exampleRequest = new ExampleRequest($request, $response, $instance);
        $instance->addExampleRequest($exampleRequest);

        $array = $instance->toArray();

        expect($array)->toHaveKey('id');
        expect($array)->toHaveKey('test_class');
        expect($array)->toHaveKey('test_method');
        expect($array)->toHaveKey('url_params');
        expect($array)->toHaveKey('query_params');
        expect($array)->toHaveKey('body_params');
        expect($array)->toHaveKey('responses');
        expect($array['test_method'])->toBe('test_create');
        expect($array['description'])->toBe('creates item');
        expect($array['body_params'])->toHaveKey('name');
    });
});

describe('toJson', function () {
    it('returns json string of array', function () {
        $route = new Route(['GET'], 'items', fn() => null);
        $route->bind(Request::create('/items'));

        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_list',
            'dataName' => '',
            'providedData' => [],
            'description' => 'lists items',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        $request = Request::create('/items', 'GET');
        $request->setRouteResolver(fn() => $route);
        $response = new Response('[]', 200);

        $exampleRequest = new ExampleRequest($request, $response, $instance);
        $instance->addExampleRequest($exampleRequest);

        $json = $instance->toJson();
        $decoded = json_decode($json, true);

        expect($decoded)->toBeArray();
        expect($decoded['test_method'])->toBe('test_list');
    });
});

describe('getWritables', function () {
    it('returns one writable file containing the complete route example', function () {
        $route = new Route(['POST'], 'items', fn() => null);
        $route->bind(Request::create('/items'));

        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_create',
            'dataName' => '',
            'providedData' => [],
            'description' => 'creates item',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        $request = Request::create('/items', 'POST', ['name' => 'Widget']);
        $request->setRouteResolver(fn() => $route);
        $response = new Response('{"id":1}', 201);

        $exampleRequest = new ExampleRequest($request, $response, $instance);
        $instance->addExampleRequest($exampleRequest);

        $writables = $instance->getWritables();

        expect($writables)
            ->toHaveCount(1)
            ->toHaveKey($instance->id . '.json')
            ->and($writables[$instance->id . '.json'])
            ->toBe($instance->toArray())
            ->toHaveKeys([
                'id',
                'test_class',
                'test_class_docblock',
                'test_method',
                'test_method_docblock',
                'url_params',
                'query_params',
                'body_params',
                'responses',
            ]);
    });
});

describe('mergeResponses', function () {
    it('deduplicates responses by description', function () {
        $route = new Route(['POST'], 'items', fn() => null);
        $route->bind(Request::create('/items'));

        $test = Mockery::mock(Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_create',
            'dataName' => '',
            'providedData' => [],
            'description' => 'creates item',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        $request1 = Request::create('/items', 'POST', ['name' => 'A']);
        $request1->setRouteResolver(fn() => $route);
        $response1 = new Response('{"id":1}', 201);

        $request2 = Request::create('/items', 'POST', ['name' => 'B']);
        $request2->setRouteResolver(fn() => $route);
        $response2 = new Response('{"id":2}', 201);

        $instance->addExampleRequest(new ExampleRequest($request1, $response1, $instance));
        $instance->addExampleRequest(new ExampleRequest($request2, $response2, $instance));

        $array = $instance->toArray();

        // Both have same description format so should be deduplicated
        expect($array['responses'])->toHaveCount(1);
    });
});
