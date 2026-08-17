<?php

return [
    'enabled' => env('SCRIBE_TDD_ENABLED', true),
    'artifact_directory' => env('SCRIBE_TDD_ARTIFACT_DIRECTORY', storage_path('scribe-tdd')),
];
