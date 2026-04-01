<?php

use AjCastro\ScribeTdd\Exceptions\LaravelNotPresent;

it('extends BadMethodCallException', function () {
    $exception = new LaravelNotPresent();

    expect($exception)->toBeInstanceOf(BadMethodCallException::class);
});

it('contains helpful error message', function () {
    $exception = new LaravelNotPresent();

    expect($exception->getMessage())->toContain('ScribeTdd');
    expect($exception->getMessage())->toContain('Laravel');
    expect($exception->getMessage())->toContain('TestCase');
});
