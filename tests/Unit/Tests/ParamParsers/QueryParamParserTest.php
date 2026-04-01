<?php

use AjCastro\ScribeTdd\Tests\ParamParsers\QueryParamParser;

it('parses string value', function () {
    $result = QueryParamParser::parse('hello');

    expect($result)->toBe([
        'type' => 'string',
        'description' => '',
        'example' => 'hello',
        'required' => false,
    ]);
});

it('parses integer value', function () {
    $result = QueryParamParser::parse(42);

    expect($result['type'])->toBe('integer');
    expect($result['example'])->toBe(42);
});

it('parses boolean value', function () {
    $result = QueryParamParser::parse(true);

    expect($result['type'])->toBe('boolean');
    expect($result['example'])->toBeTrue();
});

it('parses array of scalars', function () {
    $result = QueryParamParser::parse([1, 2, 3]);

    expect($result['type'])->toBe('integer[]');
    expect($result['example'])->toBe([1, 2, 3]);
});

it('returns null for associative arrays', function () {
    $result = QueryParamParser::parse(['key' => 'value']);

    expect($result)->toBeNull();
});
