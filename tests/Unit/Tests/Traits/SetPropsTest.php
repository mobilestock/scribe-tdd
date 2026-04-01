<?php

use AjCastro\ScribeTdd\Tests\Traits\SetProps;

it('sets multiple properties on an object', function () {
    $obj = new class {
        use SetProps;
        public string $name = '';
        public int $age = 0;
    };

    $result = $obj->setProps(['name' => 'John', 'age' => 30]);

    expect($obj->name)->toBe('John');
    expect($obj->age)->toBe(30);
    expect($result)->toBe($obj);
});

it('returns self for fluent chaining', function () {
    $obj = new class {
        use SetProps;
        public string $value = '';
    };

    $chain = $obj->setProps(['value' => 'test']);

    expect($chain)->toBe($obj);
});
