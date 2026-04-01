<?php

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use AjCastro\ScribeTdd\TestResults\RouteTestResult;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;

function setupTestResultForRoute(Route $route, string $testClass, string $testMethod): void
{
    $dir = ExampleCreator::writeDir($route);
    File::makeDirectory($dir, 0755, true, true);

    $data = [
        'test_class' => $testClass,
        'test_method' => $testMethod,
        'url_params' => [],
        'query_params' => [],
        'body_params' => [],
        'responses' => [['status' => 200, 'content' => '{"ok":true}', 'description' => '200, test']],
    ];

    File::put($dir . '/test-result.json', json_encode($data, JSON_PRETTY_PRINT));
}

function cleanupTestResultForRoute(Route $route): void
{
    $dir = ExampleCreator::writeDir($route);
    if (File::isDirectory($dir)) {
        File::deleteDirectory($dir);
    }
}

function resetRouteTestResultCache(): void
{
    $reflection = new ReflectionClass(RouteTestResult::class);
    $property = $reflection->getProperty('cache');
    $property->setValue(null, []);
}
