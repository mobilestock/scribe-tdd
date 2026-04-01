<?php

use Illuminate\Support\Facades\File;

it('deletes generated files but preserves -@ files', function () {
    $dir = storage_path('scribe-tdd/test-delete');
    File::makeDirectory($dir, 0755, true, true);

    File::put($dir . '/04-responses-@test_method.json', '{"responses":[]}');
    File::put($dir . '/00-extra-@.json', '{"keep":"me"}');

    $this->artisan('scribe:tdd:delete')
        ->expectsOutput('Successfully deleted generated files from scribe-tdd. :-)')
        ->assertExitCode(0);

    expect(File::exists($dir . '/00-extra-@.json'))->toBeTrue();
    expect(File::exists($dir . '/04-responses-@test_method.json'))->toBeFalse();

    File::deleteDirectory($dir);
});

it('removes empty directories after deletion', function () {
    $dir = storage_path('scribe-tdd/test-empty-dir');
    File::makeDirectory($dir, 0755, true, true);

    File::put($dir . '/some-file.json', '{}');

    $this->artisan('scribe:tdd:delete')->assertExitCode(0);

    expect(File::isDirectory($dir))->toBeFalse();
});
