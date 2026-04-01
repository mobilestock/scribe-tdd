<?php

use AjCastro\ScribeTdd\Writing\ScribeTddBaseGenerator;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->generator = app(ScribeTddBaseGenerator::class);
});

describe('operationId', function () {
    it('strips Controller suffix from controller name', function () {
        Route::get('/invoices', 'App\Http\Controllers\InvoiceController@index');

        $endpoint = makeOutputEndpointData([
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

        $endpoint = makeOutputEndpointData([
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

        $endpoint = makeOutputEndpointData([
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

    it('generates URI-based operationId when no route matches', function () {
        Route::post('/only-post', 'App\Http\Controllers\OnlyPostController@store');

        $endpoint = makeOutputEndpointData([
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

        expect($result)->toBe('getOnlyPost');
    });

    it('generates URI-based operationId for closure routes', function () {
        Route::get('/health', fn() => 'ok');

        $endpoint = makeOutputEndpointData([
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

        expect($result)->toBe('getHealth');
    });

    it('uses HTTP method as operationId for root URI', function () {
        Route::get('/', fn() => 'ok');

        $endpoint = makeOutputEndpointData([
            'uri' => '/',
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

        expect($result)->toBe('get');
    });

    it('generates camelCase operationId for multi-segment URI closure', function () {
        Route::post('/api/v1/webhook', fn() => 'ok');

        $endpoint = makeOutputEndpointData([
            'uri' => 'api/v1/webhook',
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

        expect($result)->toBe('postApiV1Webhook');
    });
});

describe('generateResponseContentSpec', function () {
    it('flattens PSR-7 array headers to strings', function () {
        $endpoint = makeOutputEndpointData([
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

describe('generateEndpointParametersSpec', function () {
    it('appends brackets for array query params', function () {
        $endpoint = makeOutputEndpointData([
            'uri' => 'test',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => ['X-Custom' => 'value'],
            'urlParameters' => [],
            'queryParameters' => [
                'ids' => [
                    'name' => 'ids',
                    'type' => 'integer[]',
                    'description' => 'List of IDs',
                    'required' => false,
                    'example' => [1, 2],
                ],
            ],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'generateEndpointParametersSpec');
        $result = $reflection->invoke($this->generator, $endpoint);

        $queryParam = collect($result)->firstWhere('name', 'ids[]');
        expect($queryParam)->not->toBeNull();
        expect($queryParam['name'])->toBe('ids[]');
    });

    it('does not append brackets for non-array params', function () {
        $endpoint = makeOutputEndpointData([
            'uri' => 'test',
            'httpMethods' => ['GET'],
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [
                'page' => [
                    'name' => 'page',
                    'type' => 'integer',
                    'description' => 'Page number',
                    'required' => false,
                    'example' => 1,
                ],
            ],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'generateEndpointParametersSpec');
        $result = $reflection->invoke($this->generator, $endpoint);

        $queryParam = collect($result)->firstWhere('in', 'query');
        expect($queryParam['name'])->toBe('page');
    });
});

describe('generateSchemaForResponseValue', function () {
    it('adds int64 format for large integers', function () {
        $endpoint = makeOutputEndpointData([
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
        $endpoint = makeOutputEndpointData([
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
