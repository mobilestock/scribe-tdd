<?php

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use Illuminate\Routing\Route;

beforeEach(function () {
    ExampleCreator::flushInstances();
    ExampleCreator::$currentInstance = null;
});

describe('normalizeUriForInstanceKey', function () {
    it('replaces slashes with tildes and includes methods', function () {
        $route = new Route(['GET', 'HEAD'], 'orders/{id}', fn () => null);

        $key = ExampleCreator::normalizeUriForInstanceKey($route);

        expect($key)->toBe('orders~{id},GET,HEAD');
    });

    it('replaces question marks with dots', function () {
        $route = new Route(['GET', 'HEAD'], 'users/{user?}', fn () => null);

        $key = ExampleCreator::normalizeUriForInstanceKey($route);

        expect($key)->toContain('{user.}');
    });
});

describe('writeDir', function () {
    it('returns storage path with normalized route key', function () {
        $route = new Route(['POST'], 'payments/process', fn () => null);

        $dir = ExampleCreator::writeDir($route);

        expect($dir)->toContain('scribe-tdd/payments~process,POST');
    });
});

describe('getInstanceForRoute', function () {
    it('creates new instance per test for same route', function () {
        $route = new Route(['POST'], 'orders', fn () => null);

        // Simulate first test
        $test1 = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
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
        $test2 = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
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
        $route = new Route(['POST'], 'items', fn () => null);

        $test = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
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
        $route = new Route(['GET'], 'test', fn () => null);

        $test = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
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

describe('makeId', function () {
    it('creates id from class, method and dataName', function () {
        $test = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
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
        $test = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_example',
            'dataName' => '',
            'providedData' => [],
            'description' => 'test',
        ]);

        expect($instance->id)->not->toContain('--dataset');
    });
});
