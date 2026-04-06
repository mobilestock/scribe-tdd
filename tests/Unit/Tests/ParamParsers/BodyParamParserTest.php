<?php

use AjCastro\ScribeTdd\Tests\ParamParsers\BodyParamParser;

it('parses scalar values', function () {
    $result = BodyParamParser::parse([
        'name' => 'John',
        'age' => 30,
        'active' => true,
        'rate' => 9.5,
    ]);

    expect($result)->toHaveKeys(['name', 'age', 'active', 'rate']);
    expect($result['name'])->toBe(['type' => 'string', 'description' => '', 'example' => 'John']);
    expect($result['age'])->toBe(['type' => 'integer', 'description' => '', 'example' => 30]);
    expect($result['active'])->toBe(['type' => 'boolean', 'description' => '', 'example' => true]);
    expect($result['rate'])->toBe(['type' => 'double', 'description' => '', 'example' => 9.5]);
});

it('parses nested associative arrays with dot notation', function () {
    $result = BodyParamParser::parse([
        'address' => [
            'street' => '123 Main St',
            'city' => 'NYC',
        ],
    ]);

    expect($result)->toHaveKeys(['address.street', 'address.city']);
    expect($result['address.street']['example'])->toBe('123 Main St');
    expect($result['address.city']['example'])->toBe('NYC');
});

it('parses arrays of scalars with array type suffix', function () {
    $result = BodyParamParser::parse([
        'tags' => ['php', 'laravel'],
    ]);

    expect($result['tags']['type'])->toBe('string[]');
    expect($result['tags']['example'])->toBe(['php', 'laravel']);
});

it('parses arrays of objects with object[] type', function () {
    $result = BodyParamParser::parse([
        'items' => [['name' => 'Item 1', 'qty' => 2], ['name' => 'Item 2', 'qty' => 3]],
    ]);

    expect($result['items']['type'])->toBe('object[]');
    expect($result['items']['example'])->toBeArray();
});

it('handles deeply nested structures', function () {
    $result = BodyParamParser::parse([
        'order' => [
            'customer' => [
                'name' => 'John',
            ],
        ],
    ]);

    expect($result)->toHaveKey('order.customer.name');
    expect($result['order.customer.name']['example'])->toBe('John');
});

it('returns empty array for empty input', function () {
    expect(BodyParamParser::parse([]))->toBe([]);
});
