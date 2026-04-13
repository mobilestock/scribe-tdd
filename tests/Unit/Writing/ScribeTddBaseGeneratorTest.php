<?php

use AjCastro\ScribeTdd\Writing\ScribeTddBaseGenerator;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->generator = app(ScribeTddBaseGenerator::class);
});

describe('operationId', function () {
    it('strips Controller suffix from controller name', function () {
        Route::get('/invoices', 'App\Http\Controllers\InvoiceController@index');

        $endpoint = makeOutputEndpointData(['uri' => 'invoices', 'httpMethods' => ['GET']]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('InvoiceIndex');
    });

    it('keeps controller name without Controller suffix unchanged', function () {
        Route::post('/domains', 'App\Http\Controllers\Domain@store');

        $endpoint = makeOutputEndpointData(['uri' => 'domains', 'httpMethods' => ['POST']]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('DomainStore');
    });

    it('appends model name for batching routes', function () {
        Route::get('/batching/invoices', 'App\Http\Controllers\BatchingController@index');

        $endpoint = makeOutputEndpointData(['uri' => 'batching/invoices', 'httpMethods' => ['GET']]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('BatchingIndexInvoices');
    });

    it('generates URI-based operationId when no route matches', function () {
        Route::post('/only-post', 'App\Http\Controllers\OnlyPostController@store');

        $endpoint = makeOutputEndpointData(['uri' => 'only-post', 'httpMethods' => ['GET']]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('getOnlyPost');
    });

    it('generates URI-based operationId for closure routes', function () {
        Route::get('/health', fn() => 'ok');

        $endpoint = makeOutputEndpointData(['uri' => 'health', 'httpMethods' => ['GET']]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('getHealth');
    });

    it('uses HTTP method as operationId for root URI', function () {
        Route::get('/', fn() => 'ok');

        $endpoint = makeOutputEndpointData(['uri' => '/', 'httpMethods' => ['GET']]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('get');
    });

    it('generates camelCase operationId for multi-segment URI closure', function () {
        Route::post('/api/v1/webhook', fn() => 'ok');

        $endpoint = makeOutputEndpointData(['uri' => 'api/v1/webhook', 'httpMethods' => ['POST']]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('postApiV1Webhook');
    });

    it('strips path parameters from URI-based operationId', function () {
        Route::get('/items/{id}', fn() => 'ok');

        $endpoint = makeOutputEndpointData(['uri' => 'items/{id}', 'httpMethods' => ['GET']]);

        $result = (new ReflectionMethod($this->generator, 'operationId'))->invoke($this->generator, $endpoint);

        expect($result)->toBe('getItems');
    });
});

describe('generateResponseContentSpec', function () {
    it('flattens PSR-7 array headers to strings', function () {
        $endpoint = makeOutputEndpointData([
            'uri' => 'test',
            'httpMethods' => ['GET'],
            'responses' => [
                [
                    'status' => 200,
                    'content' => '{"ok":true}',
                    'headers' => ['Content-Type' => ['application/json']],
                    'description' => '',
                ],
            ],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'generateResponseContentSpec');
        $result = $reflection->invoke($this->generator, '{"ok":true}', $endpoint);

        expect($endpoint->responses[0]->headers['Content-Type'])->toBe('application/json');
        expect($result)->toBeArray();
    });
});

describe('generateEndpointParametersSpec', function () {
    it('appends brackets for array query params', function () {
        $endpoint = makeOutputEndpointData([
            'uri' => 'test',
            'httpMethods' => ['GET'],
            'headers' => ['X-Custom' => 'value'],
            'queryParameters' => [
                'ids' => [
                    'name' => 'ids',
                    'type' => 'integer[]',
                    'description' => 'List of IDs',
                    'required' => false,
                    'example' => [1, 2],
                ],
            ],
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
            'queryParameters' => [
                'page' => [
                    'name' => 'page',
                    'type' => 'integer',
                    'description' => 'Page number',
                    'required' => false,
                    'example' => 1,
                ],
            ],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'generateEndpointParametersSpec');
        $result = $reflection->invoke($this->generator, $endpoint);

        $queryParam = collect($result)->firstWhere('in', 'query');
        expect($queryParam['name'])->toBe('page');
    });
});

describe('generateSchemaForResponseValue', function () {
    it('adds int64 format for large integers', function () {
        $endpoint = makeOutputEndpointData(['uri' => 'test', 'httpMethods' => ['GET']]);

        $largeInt = 2 ** 32 + 1;
        $result = $this->generator->generateSchemaForResponseValue($largeInt, $endpoint, 'test');

        expect($result['format'])->toBe('int64');
    });

    it('does not add int64 format for small integers', function () {
        $endpoint = makeOutputEndpointData(['uri' => 'test', 'httpMethods' => ['GET']]);

        $result = $this->generator->generateSchemaForResponseValue(42, $endpoint, 'test');

        expect($result)->not->toHaveKey('format');
    });
});

describe('generateEndpointResponsesSpec', function () {
    it('injects title with operationId and reason phrase into response schemas', function () {
        Route::get('/invoices', 'App\\Http\\Controllers\\InvoiceController@index');

        $endpoint = makeOutputEndpointData([
            'uri' => 'invoices',
            'httpMethods' => ['GET'],
            'responses' => [
                [
                    'status' => 200,
                    'content' => '{"total":10}',
                    'headers' => ['Content-Type' => 'application/json'],
                    'description' => '',
                ],
            ],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'generateEndpointResponsesSpec');
        $result = $reflection->invoke($this->generator, $endpoint);

        expect($result[200]['content']['application/json']['schema']['title'])->toBe('InvoiceIndex');
    });

    it('derives reason phrase from Symfony Response for all HTTP status codes', function () {
        Route::post('/invoices', 'App\\Http\\Controllers\\InvoiceController@store');

        $endpoint = makeOutputEndpointData([
            'uri' => 'invoices',
            'httpMethods' => ['POST'],
            'responses' => [
                [
                    'status' => 201,
                    'content' => '{"id":1}',
                    'headers' => ['Content-Type' => 'application/json'],
                    'description' => '',
                ],
                [
                    'status' => 422,
                    'content' => '{"message":"error"}',
                    'headers' => ['Content-Type' => 'application/json'],
                    'description' => '',
                ],
            ],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'generateEndpointResponsesSpec');
        $result = $reflection->invoke($this->generator, $endpoint);

        expect($result[201]['content']['application/json']['schema']['title'])->toBe('InvoiceStoreCreated');
        expect($result[422]['content']['application/json']['schema']['title'])->toBe(
            'InvoiceStoreUnprocessableContent'
        );
    });

    it('skips responses without content like 204', function () {
        Route::delete('/invoices/{id}', 'App\\Http\\Controllers\\InvoiceController@destroy');

        $endpoint = makeOutputEndpointData([
            'uri' => 'invoices/{id}',
            'httpMethods' => ['DELETE'],
            'responses' => [
                [
                    'status' => 204,
                    'content' => '',
                    'headers' => [],
                    'description' => '',
                ],
            ],
        ]);

        $reflection = new ReflectionMethod($this->generator, 'generateEndpointResponsesSpec');
        $result = $reflection->invoke($this->generator, $endpoint);

        expect($result)->toHaveKey(204);
        expect($result[204])->not->toHaveKey('content');
    });
});

describe('reasonPhrase', function () {
    it('maps all common HTTP status codes to PascalCase', function () {
        $reflection = new ReflectionMethod($this->generator, 'reasonPhrase');

        expect($reflection->invoke($this->generator, 200))->toBe('OK');
        expect($reflection->invoke($this->generator, 201))->toBe('Created');
        expect($reflection->invoke($this->generator, 202))->toBe('Accepted');
        expect($reflection->invoke($this->generator, 204))->toBe('NoContent');
        expect($reflection->invoke($this->generator, 301))->toBe('MovedPermanently');
        expect($reflection->invoke($this->generator, 400))->toBe('BadRequest');
        expect($reflection->invoke($this->generator, 401))->toBe('Unauthorized');
        expect($reflection->invoke($this->generator, 403))->toBe('Forbidden');
        expect($reflection->invoke($this->generator, 404))->toBe('NotFound');
        expect($reflection->invoke($this->generator, 409))->toBe('Conflict');
        expect($reflection->invoke($this->generator, 422))->toBe('UnprocessableContent');
        expect($reflection->invoke($this->generator, 429))->toBe('TooManyRequests');
        expect($reflection->invoke($this->generator, 500))->toBe('InternalServerError');
        expect($reflection->invoke($this->generator, 502))->toBe('BadGateway');
        expect($reflection->invoke($this->generator, 503))->toBe('ServiceUnavailable');
    });

    it('falls back to status code string for unknown codes', function () {
        $reflection = new ReflectionMethod($this->generator, 'reasonPhrase');

        expect($reflection->invoke($this->generator, 599))->toBe('599');
    });
});
