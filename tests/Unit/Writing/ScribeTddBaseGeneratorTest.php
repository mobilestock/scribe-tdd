<?php

use AjCastro\ScribeTdd\Writing\ScribeTddBaseGenerator;
use Illuminate\Support\Facades\Route;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;

beforeEach(function () {
    $this->generator = app(ScribeTddBaseGenerator::class);
});

describe('operationId', function () {
    it('strips Controller suffix from controller name', function () {
        Route::get('/invoices', 'App\Http\Controllers\InvoiceController@index');

        $endpoint = new OutputEndpointData([
            'uri' => 'invoices',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('InvoiceIndex');
    });

    it('keeps controller name without Controller suffix unchanged', function () {
        Route::post('/domains', 'App\Http\Controllers\Domain@store');

        $endpoint = new OutputEndpointData([
            'uri' => 'domains',
            'httpMethods' => ['POST'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('DomainStore');
    });

    it('appends model name for batching routes', function () {
        Route::get('/batching/invoices', 'App\Http\Controllers\BatchingController@index');

        $endpoint = new OutputEndpointData([
            'uri' => 'batching/invoices',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('BatchingIndexInvoices');
    });

    it('skips routes with matching URI but different HTTP methods', function () {
        Route::post('/only-post', 'App\Http\Controllers\OnlyPostController@store');

        $endpoint = new OutputEndpointData([
            'uri' => 'only-post',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        // No matching route for GET only-post, falls back to parent
        expect($result)->toBeString();
    });

    it('falls back to parent for closure routes', function () {
        Route::get('/health', fn() => 'ok');

        $endpoint = new OutputEndpointData([
            'uri' => 'health',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        // Parent generates from URI
        expect($result)->toBeString();
    });
});

describe('generateResponseContentSpec', function () {
    it('flattens PSR-7 array headers to strings', function () {
        $endpoint = new OutputEndpointData([
            'uri' => 'test',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [
                [
                    'status' => 200,
                    'content' => '{"ok":true}',
                    'headers' => ['Content-Type' => ['application/json']],
                    'description' => '',
                ],
            ],
            'responseFields' => [],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'generateResponseContentSpec');
        $result = $reflection->invoke($this->generator, '{"ok":true}', $endpoint);

        // Headers should be flattened
        expect($endpoint->responses[0]->headers['Content-Type'])->toBe('application/json');
        expect($result)->toBeArray();
    });
});

describe('queryParamToOpenApiParameterObject', function () {
    it('appends brackets for array query params', function () {
        $param = new Parameter([
            'name' => 'ids',
            'type' => 'integer[]',
            'description' => 'List of IDs',
            'required' => false,
            'example' => [1, 2],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'queryParamToOpenApiParameterObject');
        $result = $reflection->invoke($this->generator, 'ids', $param);

        expect($result['name'])->toBe('ids[]');
    });

    it('does not append brackets for non-array params', function () {
        $param = new Parameter([
            'name' => 'page',
            'type' => 'integer',
            'description' => 'Page number',
            'required' => false,
            'example' => 1,
        ]);

        $reflection = new ReflectionMethod($this->generator, 'queryParamToOpenApiParameterObject');
        $result = $reflection->invoke($this->generator, 'page', $param);

        expect($result['name'])->toBe('page');
    });
});

describe('generateSchemaForResponseValue', function () {
    it('adds int64 format for large integers', function () {
        $endpoint = new OutputEndpointData([
            'uri' => 'test',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $largeInt = 2 ** 32 + 1;
        $result = $this->generator->generateSchemaForResponseValue($largeInt, $endpoint, 'test');

        expect($result['format'])->toBe('int64');
    });

    it('does not add int64 format for small integers', function () {
        $endpoint = new OutputEndpointData([
            'uri' => 'test',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = $this->generator->generateSchemaForResponseValue(42, $endpoint, 'test');

        expect($result)->not->toHaveKey('format');
    });
});
