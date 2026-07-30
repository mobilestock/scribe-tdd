<?php

use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Reset static cache between tests
    $reflection = new ReflectionClass(RouteTestResult::class);
    $property = $reflection->getProperty('cache');
    $property->setAccessible(true);
    $property->setValue(null, []);
});

describe('loadTestResults', function () {
    it('merges params from multiple files', function () {
        $dir = storage_path('scribe-tdd/test-merge');
        File::makeDirectory($dir, 0755, true, true);

        File::put(
            $dir . '/file1.json',
            json_encode([
                'test_class' => 'TestClass1',
                'test_method' => 'testMethod1',
                'url_params' => ['id' => ['type' => 'integer', 'example' => 1]],
                'query_params' => ['page' => ['type' => 'integer', 'example' => 1]],
                'body_params' => ['name' => ['type' => 'string', 'example' => 'John']],
                'responses' => [['status' => 200, 'content' => '{"ok":true}']],
            ])
        );

        File::put(
            $dir . '/file2.json',
            json_encode([
                'test_class' => 'TestClass2',
                'test_method' => 'testMethod2',
                'url_params' => ['slug' => ['type' => 'string', 'example' => 'abc']],
                'query_params' => ['limit' => ['type' => 'integer', 'example' => 10]],
                'body_params' => ['email' => ['type' => 'string', 'example' => 'a@b.c']],
                'responses' => [['status' => 201, 'content' => '{"created":true}']],
            ])
        );

        $result = RouteTestResult::loadTestResults($dir);

        expect($result['url_params'])->toHaveKeys(['id', 'slug']);
        expect($result['query_params'])->toHaveKeys(['page', 'limit']);
        expect($result['body_params'])->toHaveKeys(['name', 'email']);
        expect($result['responses'])->toHaveCount(2);

        File::deleteDirectory($dir);
    });

    it('deduplicates responses by status code', function () {
        $dir = storage_path('scribe-tdd/test-dedup');
        File::makeDirectory($dir, 0755, true, true);

        File::put(
            $dir . '/file1.json',
            json_encode([
                'responses' => [['status' => 200, 'content' => '{"data":"first"}']],
            ])
        );

        File::put(
            $dir . '/file2.json',
            json_encode([
                'responses' => [['status' => 200, 'content' => '{"data":"second"}']],
            ])
        );

        File::put(
            $dir . '/file3.json',
            json_encode([
                'responses' => [['status' => 422, 'content' => '{"error":"fail"}']],
            ])
        );

        $result = RouteTestResult::loadTestResults($dir);

        expect($result['responses'])->toHaveCount(2);
        expect($result['responses'][0]['status'])->toBe(200);
        expect($result['responses'][0]['content'])->toBe('{"data":"first"}');
        expect($result['responses'][1]['status'])->toBe(422);

        File::deleteDirectory($dir);
    });

    it('keeps first occurrence of duplicate status codes', function () {
        $dir = storage_path('scribe-tdd/test-first');
        File::makeDirectory($dir, 0755, true, true);

        File::put(
            $dir . '/01-file.json',
            json_encode([
                'responses' => [['status' => 200, 'content' => '{"winner":"first"}']],
            ])
        );

        File::put(
            $dir . '/02-file.json',
            json_encode([
                'responses' => [['status' => 200, 'content' => '{"loser":"second"}']],
            ])
        );

        $result = RouteTestResult::loadTestResults($dir);

        expect($result['responses'])->toHaveCount(1);
        expect($result['responses'][0]['content'])->toBe('{"winner":"first"}');

        File::deleteDirectory($dir);
    });

    it('preserves url params with + operator (first key wins)', function () {
        $dir = storage_path('scribe-tdd/test-plus');
        File::makeDirectory($dir, 0755, true, true);

        File::put(
            $dir . '/file1.json',
            json_encode([
                'body_params' => ['name' => ['type' => 'string', 'example' => 'First']],
            ])
        );

        File::put(
            $dir . '/file2.json',
            json_encode([
                'body_params' => ['name' => ['type' => 'string', 'example' => 'Second']],
            ])
        );

        $result = RouteTestResult::loadTestResults($dir);

        expect($result['body_params']['name']['example'])->toBe('First');

        File::deleteDirectory($dir);
    });

    it('returns empty structure for empty directory', function () {
        $dir = storage_path('scribe-tdd/test-empty');
        File::makeDirectory($dir, 0755, true, true);

        $result = RouteTestResult::loadTestResults($dir);

        expect($result['test_class'])->toBe('');
        expect($result['test_method'])->toBe('');
        expect($result['url_params'])->toBe([]);
        expect($result['responses'])->toBe([]);

        File::deleteDirectory($dir);
    });
});

describe('getTestResultForRoute', function () {
    it('returns cached result on second call', function () {
        $route = new Illuminate\Routing\Route(['GET'], 'cache-test', fn() => null);
        $dir = AjCastro\ScribeTdd\Tests\ExampleCreator::writeDir($route);

        File::makeDirectory($dir, 0755, true, true);
        File::put(
            $dir . '/result.json',
            json_encode([
                'test_class' => 'CacheClass',
                'test_method' => 'testCache',
                'url_params' => [],
                'query_params' => [],
                'body_params' => [],
                'responses' => [],
            ])
        );

        // First call - loads from disk
        $result1 = RouteTestResult::getTestResultForRoute($route);
        expect($result1['test_class'])->toBe('CacheClass');

        // Remove the file to prove second call uses cache
        File::deleteDirectory($dir);

        // Second call - should return cached result
        $result2 = RouteTestResult::getTestResultForRoute($route);
        expect($result2['test_class'])->toBe('CacheClass');
    });
});

describe('getTestDocBlocks', function () {
    it('loads persisted docblocks without the test class', function () {
        $route = new Illuminate\Routing\Route(['GET'], 'docblock-test', fn() => null);
        $testResult = [
            'test_class' => 'P\\Tests\\MissingTest',
            'test_class_docblock' => "/**\n * @group Items\n */",
            'test_method' => '__pest_evaluable_test',
            'test_method_docblock' => "/**\n * @urlParam id integer required The item ID.\n */",
        ];

        $docBlocks = RouteTestResult::getTestDocBlocks($route, $testResult);

        expect($docBlocks['class']->getTagsByName('group'))->toHaveCount(1)
            ->and($docBlocks['method']->getTagsByName('urlParam'))->toHaveCount(1);
    });

    it('returns empty docblocks for legacy manifests with an unavailable test class', function () {
        $route = new Illuminate\Routing\Route(['GET'], 'legacy-docblock-test', fn() => null);
        $testResult = [
            'test_class' => 'P\\Tests\\MissingTest',
            'test_method' => '__pest_evaluable_test',
        ];

        $docBlocks = RouteTestResult::getTestDocBlocks($route, $testResult);

        expect($docBlocks['class']->getTags())->toBeEmpty()
            ->and($docBlocks['method']->getTags())->toBeEmpty();
    });
});

describe('decodeFile', function () {
    it('decodes JSON file to array', function () {
        $path = storage_path('scribe-tdd/test-decode.json');
        File::put($path, json_encode(['key' => 'value']));

        $result = RouteTestResult::decodeFile($path);

        expect($result)->toBe(['key' => 'value']);

        File::delete($path);
    });
});
