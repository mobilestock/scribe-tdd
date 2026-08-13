<?php

namespace AjCastro\ScribeTdd\TestResults;

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Mpociot\Reflection\DocBlock;

class RouteTestResult
{
    protected static $cache = [];

    public static function getTestResultForRoute(Route $route)
    {
        $dir = ExampleCreator::writeDir($route);
        if ($result = static::$cache[$dir] ?? null) {
            return $result;
        }

        if (File::missing($dir)) {
            return [];
        }

        return static::$cache[$dir] = static::loadTestResults($dir);
    }

    public static function loadTestResults($dir)
    {
        $result = [
            'test_class' => '',
            'test_class_docblock' => null,
            'test_method' => '',
            'test_method_docblock' => null,
            'url_params' => [],
            'query_params' => [],
            'body_params' => [],
            'responses' => [],
        ];

        $files = File::files($dir);
        $seenStatusCodes = [];

        foreach ($files as $file) {
            $array = static::decodeFile($file->getPathname());

            $result['test_class'] = $array['test_class'] ?? $result['test_class'];
            $result['test_class_docblock'] = $array['test_class_docblock'] ?? $result['test_class_docblock'];
            $result['test_method'] = $array['test_method'] ?? $result['test_method'];
            $result['test_method_docblock'] = $array['test_method_docblock'] ?? $result['test_method_docblock'];

            $result['url_params'] = $result['url_params'] + ($array['url_params'] ?? []);
            $result['query_params'] = $result['query_params'] + ($array['query_params'] ?? []);
            $result['body_params'] = $result['body_params'] + ($array['body_params'] ?? []);

            foreach ($array['responses'] ?? [] as $response) {
                $statusCode = $response['status'] ?? null;
                if (!isset($seenStatusCodes[$statusCode])) {
                    $result['responses'][] = $response;
                    $seenStatusCodes[$statusCode] = true;
                }
            }
        }

        return $result;
    }

    /**
     * @param array{
     *     test_class: string,
     *     test_class_docblock?: string|null,
     *     test_method: string,
     *     test_method_docblock?: string|null
     * } $testResult
     * @return array{method: DocBlock, class: ?DocBlock}
     */
    public static function getTestDocBlocks(Route $route, array $testResult): array
    {
        if (($testResult['test_method_docblock'] ?? null) !== null
            || ($testResult['test_class_docblock'] ?? null) !== null) {
            return [
                'method' => new DocBlock($testResult['test_method_docblock'] ?? ''),
                'class' => new DocBlock($testResult['test_class_docblock'] ?? ''),
            ];
        }

        $testClass = $testResult['test_class'];
        $testMethod = $testResult['test_method'];

        if (class_exists($testClass) && method_exists($testClass, $testMethod)) {
            return RouteDocBlocker::getDocBlocks($route, [$testClass, $testMethod]);
        }

        return [
            'method' => new DocBlock(''),
            'class' => new DocBlock(''),
        ];
    }

    public static function decodeFile($filepath)
    {
        return json_decode(File::get($filepath), true);
    }
}
