<?php

namespace AjCastro\ScribeTdd\Tests;

use AjCastro\ScribeTdd\Tests\Traits\SetProps;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Routing\Route;
use ReflectionClass;

class ExampleCreator implements Arrayable, Jsonable
{
    use SetProps;

    public $id;
    public $testClass;
    public string $testClassDocBlock;
    public $testMethod;
    public string $testMethodDocBlock;
    public $dataName;
    public $providedData;
    public $description;
    public Route $route;

    private $exampleRequests;
    private $test;

    public static $currentInstance;

    public static $instances = [];

    public function __construct(array $props)
    {
        $this->setProps($props);
        /** @var object $test */
        $test = $this->test;
        /** @var string $testMethod */
        $testMethod = $this->testMethod;

        $this->testClass = get_class($test);
        $reflection = new ReflectionClass($test);
        $this->testClassDocBlock = $reflection->getDocComment() ?: '';
        $this->testMethodDocBlock = $reflection->hasMethod($testMethod)
            ? ($reflection->getMethod($testMethod)->getDocComment() ?:
            '')
            : '';
        $this->id = static::makeId($this);
    }

    public static function makeId(self $instance)
    {
        $parts = array_filter([
            str_replace('\\', '~', $instance->testClass),
            $instance->testMethod,
            $instance->dataName,
        ]);

        return implode('--', $parts);
    }

    public static function getCurrentInstance()
    {
        return static::$currentInstance;
    }

    public static function setCurrentInstance(self $instance)
    {
        static::$currentInstance = $instance;
    }

    public static function normalizeUriForInstanceKey(Route $route)
    {
        $uri = str_replace('/', '~', $route->uri);
        $uri = str_replace('?', '.', $uri);

        $parts = [$uri];
        $parts = array_merge($parts, $route->methods);

        return implode(',', $parts);
    }

    public static function writeDir(Route $route)
    {
        return storage_path('scribe-tdd/' . static::normalizeUriForInstanceKey($route));
    }

    public static function getInstanceForRoute($route)
    {
        $routeKey = static::normalizeUriForInstanceKey($route);
        $currentInstance = static::getCurrentInstance();
        $instanceId = $currentInstance->id;

        // Check if this specific test already has an instance for this route
        if (isset(static::$instances[$routeKey])) {
            foreach (static::$instances[$routeKey] as $existing) {
                if ($existing->id === $instanceId) {
                    return $existing;
                }
            }
        }

        $instance = $currentInstance->setRoute($route);

        return static::registerInstance($instance);
    }

    protected static function registerInstance(self $instance)
    {
        $routeKey = $instance->instanceKey();
        static::$instances[$routeKey][] = $instance;

        return $instance;
    }

    public static function getInstances()
    {
        return static::$instances;
    }

    public static function flushInstances()
    {
        static::$instances = [];
    }

    public function addExampleRequest(ExampleRequest $exampleRequest)
    {
        $this->exampleRequests[] = $exampleRequest;

        return $this;
    }

    public function setRoute(Route $route)
    {
        $this->route = $route;

        return $this;
    }

    public function instanceKey()
    {
        return $this->normalizeUriForInstanceKey($this->route);
    }

    /** @deprecated in favor of getWritables() */
    public function writePath()
    {
        return static::writeDir($this->route) . '/' . $this->id . '.json';
    }

    protected function getExtra()
    {
        return [
            'id' => $this->id,
            'test_class' => $this->testClass,
            'test_class_docblock' => $this->testClassDocBlock,
            'test_method' => $this->testMethod,
            'test_method_docblock' => $this->testMethodDocBlock,
            'data_name' => $this->dataName,
            'provided_data' => $this->providedData,
            'description' => $this->description,
            'key' => $this->instanceKey(),
            'route' => [
                'uri' => $this->route->uri,
                'name' => $this->route->getName(),
                'methods' => $this->route->methods,
            ],
        ];
    }

    public function toArray()
    {
        return $this->getExtra() + [
            'url_params' => $this->mergeUrlParams(),
            'query_params' => $this->mergeQueryParams(),
            'body_params' => $this->mergeBodyParams(),
            'responses' => $this->mergeResponses(),
        ];
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    protected function mergeData($type)
    {
        $results = [];

        $method = 'get' . ucfirst($type);

        foreach ($this->exampleRequests as $request) {
            $results = $results + $request->{$method}();
        }

        return $results;
    }

    protected function mergeUrlParams()
    {
        return $this->mergeData('urlParams');
    }

    protected function mergeQueryParams()
    {
        return $this->mergeData('queryParams');
    }

    protected function mergeBodyParams()
    {
        return $this->mergeData('bodyParams');
    }

    protected function mergeResponses()
    {
        $results = [];

        foreach ($this->exampleRequests as $request) {
            $response = $request->getResponse();
            $description = $response['description'];

            if (!isset($results[$description])) {
                $results[$description] = $response;
            }
        }

        return array_values($results);
    }

    public function getWritables()
    {
        return [
            $this->id . '.json' => $this->toArray(),
        ];
    }
}
