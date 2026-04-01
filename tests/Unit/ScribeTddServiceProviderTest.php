<?php

use Illuminate\Support\Facades\Config;

it('registers scribe route matching to wildcard', function () {
    expect(Config::get('scribe.routes.0.match.prefixes'))->toBe(['*']);
    expect(Config::get('scribe.routes.0.match.domains'))->toBe(['*']);
});

it('excludes framework routes from documentation', function () {
    $excludeRoutes = Config::get('scribe.routes.0.exclude');

    expect($excludeRoutes)->toContain('_ignition/*');
    expect($excludeRoutes)->toContain('oauth/*');
    expect($excludeRoutes)->toContain('up');
    expect($excludeRoutes)->toContain('storage/*');
});

it('sets external static doc type with elements theme', function () {
    expect(Config::get('scribe.type'))->toBe('external_static');
    expect(Config::get('scribe.theme'))->toBe('elements');
});

it('enables bearer auth', function () {
    expect(Config::get('scribe.auth.enabled'))->toBeTrue();
    expect(Config::get('scribe.auth.in'))->toBe('bearer');
});

it('registers custom metadata strategy', function () {
    $strategies = Config::get('scribe.strategies');

    expect($strategies['metadata'])->toBe([
        AjCastro\ScribeTdd\Strategies\Metadata\GetFromRoute::class,
    ]);
});

it('registers custom body parameter strategies', function () {
    $strategies = Config::get('scribe.strategies');

    expect($strategies['bodyParameters'])->toContain(
        AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromLaravelData::class,
        AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromInlineValidator::class,
        AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromTestResult::class,
    );
});

it('registers custom response strategy', function () {
    $strategies = Config::get('scribe.strategies');

    expect($strategies['responses'])->toBe([
        AjCastro\ScribeTdd\Strategies\Responses\GetFromTestResult::class,
    ]);
});
