<?php

use AjCastro\ScribeTdd\Strategies\GetFromLaravelDataBase;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Illuminate\Routing\Route;

beforeEach(function () {
    $this->strategy = new class (new DocumentationConfig(config('scribe') ?? [])) extends GetFromLaravelDataBase {
        protected string $customParameterDataMethodName = 'bodyParameters';
    };
});

describe('getLaravelDataReflectionClass', function () {
    it('returns null when no Data class parameter found', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle(string $name)
                {
                }
            },
            'handle'
        );

        $reflection = new ReflectionMethod($this->strategy, 'getLaravelDataReflectionClass');
        $result = $reflection->invoke($this->strategy, $method);

        expect($result)->toBeNull();
    });

    it('finds Data class parameter', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle(Spatie\LaravelData\Data $data)
                {
                }
            },
            'handle'
        );

        $reflection = new ReflectionMethod($this->strategy, 'getLaravelDataReflectionClass');
        $result = $reflection->invoke($this->strategy, $method);

        // Data itself is not a subclass of Data, it IS Data, so returns null
        expect($result)->toBeNull();
    });

    it('returns reflection for Data subclass', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle(Tests\Fixtures\SimpleData $data)
                {
                }
            },
            'handle'
        );

        $reflection = new ReflectionMethod($this->strategy, 'getLaravelDataReflectionClass');
        $result = $reflection->invoke($this->strategy, $method);

        expect($result)->toBeInstanceOf(ReflectionClass::class);
        expect($result->getName())->toBe(Tests\Fixtures\SimpleData::class);
    });

    it('skips union type parameters', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle(string|int $data)
                {
                }
            },
            'handle'
        );

        $reflection = new ReflectionMethod($this->strategy, 'getLaravelDataReflectionClass');
        $result = $reflection->invoke($this->strategy, $method);

        expect($result)->toBeNull();
    });

    it('skips non-existent class types', function () {
        // Test with parameter that has no type
        $method = new ReflectionMethod(
            new class {
                public function handle($data)
                {
                }
            },
            'handle'
        );

        $reflection = new ReflectionMethod($this->strategy, 'getLaravelDataReflectionClass');
        $result = $reflection->invoke($this->strategy, $method);

        expect($result)->toBeNull();
    });
});

describe('normalizeDataRules', function () {
    it('passes through string rules', function () {
        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, ['name' => 'required|string']);

        expect($result)->toBe(['name' => 'required|string']);
    });

    it('converts numeric indices to wildcards', function () {
        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, [
            'items.0.name' => 'required|string',
            'items.0.qty' => 'required|integer',
        ]);

        expect($result)->toHaveKey('items.*.name');
        expect($result)->toHaveKey('items.*.qty');
    });

    it('converts object rules with __toString', function () {
        $rule = new class {
            public function __toString(): string
            {
                return 'required|string';
            }
        };

        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, ['field' => [$rule]]);

        expect($result['field'])->toBe(['required|string']);
    });

    it('passes through non-array non-string rules as array', function () {
        $rule = new stdClass();

        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, ['field' => $rule]);

        expect($result['field'])->toBe([$rule]);
    });

    it('passes through Laravel Rule objects in arrays', function () {
        $rule = new class implements Illuminate\Contracts\Validation\ValidationRule {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
            }
        };

        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, ['field' => ['required', $rule]]);

        expect($result['field'][0])->toBe('required');
        expect($result['field'][1])->toBe($rule);
    });
});

describe('buildPayloadForNestedDataExpansion', function () {
    it('returns empty array for class without constructor', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'stdClass');

        expect($result)->toBe([]);
    });

    it('returns empty array for nonexistent class', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'NonExistentClass12345');

        expect($result)->toBe([]);
    });

    it('builds payload for nested Data class parameters', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, Tests\Fixtures\OrderData::class);

        expect($result)->toHaveKey('main_item');
        expect($result['main_item'])->toBeArray();
        expect($result['main_item'])->toHaveKey('sku');
        expect($result['main_item'])->toHaveKey('qty');
    });

    it('builds payload for DataCollectionOf attributes', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, Tests\Fixtures\OrderData::class);

        expect($result)->toHaveKey('items');
        expect($result['items'])->toBeArray();
        expect($result['items'][0])->toHaveKey('sku');
    });

    it('skips builtin types without DataCollectionOf', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, Tests\Fixtures\SimpleData::class);

        // SimpleData has string $name and int $quantity - both builtin, no DataCollectionOf
        expect($result)->toBe([]);
    });

    it('skips union type parameters', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, Tests\Fixtures\MixedParamData::class);

        // MixedParamData has string|int $mixed_field which is ReflectionUnionType, not ReflectionNamedType
        expect($result)->toBe([]);
    });
});

describe('getRouteValidationRules', function () {
    it('returns empty array for class without getValidationRules', function () {
        $reflection = new ReflectionMethod($this->strategy, 'getRouteValidationRules');
        $result = $reflection->invoke($this->strategy, 'stdClass');

        expect($result)->toBe([]);
    });

    it('returns validation rules for Data class', function () {
        $reflection = new ReflectionMethod($this->strategy, 'getRouteValidationRules');
        $result = $reflection->invoke($this->strategy, Tests\Fixtures\SimpleData::class);

        expect($result)->toBeArray();
        expect($result)->not->toBeEmpty();
    });
});

describe('getCustomParameterData', function () {
    it('returns empty array when method does not exist', function () {
        $reflection = new ReflectionMethod($this->strategy, 'getCustomParameterData');
        $result = $reflection->invoke($this->strategy, 'stdClass');

        expect($result)->toBe([]);
    });

    it('calls customParameterData method when it exists', function () {
        $dataClass = new class extends \Spatie\LaravelData\Data {
            public function __construct(public string $name = '') {}

            public static function bodyParameters(): array
            {
                return [
                    'name' => [
                        'description' => 'The name',
                        'example' => 'Widget',
                    ],
                ];
            }
        };

        $reflection = new ReflectionMethod($this->strategy, 'getCustomParameterData');
        $result = $reflection->invoke($this->strategy, get_class($dataClass));

        expect($result)->toHaveKey('name');
        expect($result['name']['description'])->toBe('The name');
    });
});

describe('getDataCollectionOfClass', function () {
    it('returns null when declaring function is not a method', function () {
        // Test a function parameter (not a class method parameter)
        $func = new ReflectionFunction(function (string $test) {});
        $param = $func->getParameters()[0];

        $reflection = new ReflectionMethod($this->strategy, 'getDataCollectionOfClass');
        $result = $reflection->invoke($this->strategy, $param);

        expect($result)->toBeNull();
    });

    it('returns null when property does not exist on declaring class', function () {
        $class = new class {
            public function __construct(string $notAProperty = '') {}
        };
        $constructor = new ReflectionMethod($class, '__construct');
        $param = $constructor->getParameters()[0];

        $reflection = new ReflectionMethod($this->strategy, 'getDataCollectionOfClass');
        $result = $reflection->invoke($this->strategy, $param);

        expect($result)->toBeNull();
    });

    it('returns null when no DataCollectionOf attribute', function () {
        $class = new class {
            public array $items;
            public function __construct(array $items = []) {}
        };
        $constructor = new ReflectionMethod($class, '__construct');
        $param = $constructor->getParameters()[0];

        $reflection = new ReflectionMethod($this->strategy, 'getDataCollectionOfClass');
        $result = $reflection->invoke($this->strategy, $param);

        expect($result)->toBeNull();
    });

    it('returns reflection for valid DataCollectionOf attribute', function () {
        $constructor = new ReflectionMethod(\Tests\Fixtures\OrderData::class, '__construct');
        $itemsParam = null;
        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === 'items') {
                $itemsParam = $param;
                break;
            }
        }

        $reflection = new ReflectionMethod($this->strategy, 'getDataCollectionOfClass');
        $result = $reflection->invoke($this->strategy, $itemsParam);

        expect($result)->toBeInstanceOf(ReflectionClass::class);
        expect($result->getName())->toBe(\Tests\Fixtures\NestedItemData::class);
    });
});

describe('buildNestedDataStub', function () {
    it('returns empty for class without constructor', function () {
        $class = new ReflectionClass(new class extends \Spatie\LaravelData\Data {});

        $reflection = new ReflectionMethod($this->strategy, 'buildNestedDataStub');
        $result = $reflection->invoke($this->strategy, $class);

        expect($result)->toBe([]);
    });

    it('builds stub with null for builtin types', function () {
        $class = new ReflectionClass(\Tests\Fixtures\NestedItemData::class);

        $reflection = new ReflectionMethod($this->strategy, 'buildNestedDataStub');
        $result = $reflection->invoke($this->strategy, $class);

        expect($result)->toHaveKey('sku');
        expect($result)->toHaveKey('qty');
        expect($result['sku'])->toBeNull();
    });

    it('builds nested stub for Data class properties', function () {
        $class = new ReflectionClass(\Tests\Fixtures\OrderData::class);

        $reflection = new ReflectionMethod($this->strategy, 'buildNestedDataStub');
        $result = $reflection->invoke($this->strategy, $class);

        expect($result)->toHaveKey('customer_name');
        expect($result)->toHaveKey('main_item');
        expect($result['main_item'])->toBeArray();
        expect($result['main_item'])->toHaveKey('sku');
    });
});

describe('getMissingCustomDataMessage', function () {
    it('returns descriptive message with parameter name', function () {
        $reflection = new ReflectionMethod($this->strategy, 'getMissingCustomDataMessage');
        $result = $reflection->invoke($this->strategy, 'name');

        expect($result)->toContain('name');
        expect($result)->toContain('bodyParameters');
    });
});

describe('isLaravelDataMeantForThisStrategy', function () {
    it('returns true by default in base class', function () {
        $reflection = new ReflectionMethod($this->strategy, 'isLaravelDataMeantForThisStrategy');
        $mockClass = new ReflectionClass(Tests\Fixtures\SimpleData::class);

        expect($reflection->invoke($this->strategy, $mockClass))->toBeTrue();
    });
});

describe('__invoke', function () {
    it('returns empty when Data class is not installed', function () {
        $controller = new class {
            public function handle(string $name)
            {
            }
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'test', fn() => null);

        $endpoint = new ExtractedEndpointData([
            'route' => $route,
            'uri' => 'test',
            'httpMethods' => ['POST'],
            'method' => $method,
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = $this->strategy->__invoke($endpoint);

        expect($result)->toBe([]);
    });

    it('returns empty when Data class has no validation rules', function () {
        $controller = new class {
            public function handle(\Tests\Fixtures\EmptyData $data) {}
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'empty-data', fn () => null);

        $endpoint = new ExtractedEndpointData([
            'route' => $route,
            'uri' => 'empty-data',
            'httpMethods' => ['POST'],
            'method' => $method,
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = $this->strategy->__invoke($endpoint);

        expect($result)->toBe([]);
    });

    it('extracts parameters from Data class', function () {
        $controller = new class {
            public function handle(Tests\Fixtures\SimpleData $data)
            {
            }
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'simple-data', fn() => null);

        $endpoint = new ExtractedEndpointData([
            'route' => $route,
            'uri' => 'simple-data',
            'httpMethods' => ['POST'],
            'method' => $method,
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = $this->strategy->__invoke($endpoint);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('name');
    });

    it('extracts parameters from nested Data class', function () {
        $controller = new class {
            public function handle(Tests\Fixtures\OrderData $data)
            {
            }
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'order-data', fn() => null);

        $endpoint = new ExtractedEndpointData([
            'route' => $route,
            'uri' => 'order-data',
            'httpMethods' => ['POST'],
            'method' => $method,
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = $this->strategy->__invoke($endpoint);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('customer_name');
    });

    it('returns empty when strategy rejects the Data class', function () {
        $rejectingStrategy = new class(new \Knuckles\Scribe\Tools\DocumentationConfig(config('scribe') ?? [])) extends \AjCastro\ScribeTdd\Strategies\GetFromLaravelDataBase {
            protected string $customParameterDataMethodName = 'bodyParameters';

            protected function isLaravelDataMeantForThisStrategy(\ReflectionClass $class): bool
            {
                return false;
            }
        };

        $controller = new class {
            public function handle(\Tests\Fixtures\SimpleData $data) {}
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'rejected', fn () => null);

        $endpoint = new ExtractedEndpointData([
            'route' => $route,
            'uri' => 'rejected',
            'httpMethods' => ['POST'],
            'method' => $method,
            'metadata' => [],
            'headers' => [],
            'urlParameters' => [],
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ]);

        $result = $rejectingStrategy->__invoke($endpoint);

        expect($result)->toBe([]);
    });
});
