<?php

use AjCastro\ScribeTdd\Strategies\GetFromLaravelDataBase;
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

    it('converts deeply nested numeric indices to wildcards', function () {
        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, [
            'items.0.sub_items.0.variants.0.name' => 'required|string',
        ]);

        expect($result)->toHaveKey('items.*.sub_items.*.variants.*.name');
    });

    it('converts multi-digit numeric indices', function () {
        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, [
            'items.10.name' => 'required|string',
            'items.99.qty' => 'required|integer',
        ]);

        expect($result)->toHaveKey('items.*.name');
        expect($result)->toHaveKey('items.*.qty');
    });

    it('converts numeric index at end of field path', function () {
        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, [
            'items.0' => 'required|integer',
        ]);

        expect($result)->toHaveKey('items.*');
    });

    it('leaves already-wildcard paths unchanged', function () {
        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, [
            'items.*.name' => 'required|string',
        ]);

        expect($result)->toHaveKey('items.*.name');
    });

    it('merges duplicate keys after wildcard conversion', function () {
        $reflection = new ReflectionMethod($this->strategy, 'normalizeDataRules');
        $result = $reflection->invoke($this->strategy, [
            'items.0.name' => 'required|string',
            'items.1.name' => 'required|string',
        ]);

        // Both convert to items.*.name — last one wins in PHP arrays
        expect($result)->toHaveKey('items.*.name');
        expect(count(array_keys($result)))->toBe(1);
    });
});

describe('buildPayloadForNestedDataExpansion', function () {
    it('returns empty array for class without constructor', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'stdClass');

        expect($result)->toBe([]);
    });

    it('throws for nonexistent class', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');

        expect(fn() => $reflection->invoke($this->strategy, 'NonExistentClass12345'))->toThrow(
            ReflectionException::class
        );
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

    it('builds payload for DataCollectionOf with DataCollection type', function () {
        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, Tests\Fixtures\DataCollectionData::class);

        expect($result)->toHaveKey('items');
        expect($result['items'])->toBeArray();
        expect($result['items'][0])->toHaveKey('sku');
        expect($result['items'][0])->toHaveKey('qty');
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

    it('skips non-existent class type hints in constructor parameters', function () {
        // PHP allows classes with type hints to non-existent classes (lazy validation)
        eval('
            class DataWithNonExistentTypeHint extends Spatie\LaravelData\Data {
                public function __construct(
                    public string $name,
                    public \NonExistent\SomeClass $thing,
                ) {}
            }
        ');

        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'DataWithNonExistentTypeHint');

        // Should skip the non-existent type without throwing, not build payload for it
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

    it('returns empty rules when ALL builtin properties have defaults (known Spatie limitation)', function () {
        eval('
            class AllDefaultsData extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $name = "default",
                    public int $quantity = 0,
                    public ?string $notes = null,
                ) {}
            }
        ');

        $reflection = new ReflectionMethod($this->strategy, 'getRouteValidationRules');
        $result = $reflection->invoke($this->strategy, 'AllDefaultsData');

        // Spatie omits properties with defaults when not present in payload.
        // buildPayloadForNestedDataExpansion only fills nested Data keys, not builtin keys.
        // This is a pre-existing limitation: optional builtin properties won't appear in docs.
        expect($result)->toBe([]);
    });

    it('includes nested Data rules even when nested property has default', function () {
        eval('
            class InnerDefaultData extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $street = "",
                    public string $city = "",
                ) {}
            }
            class OuterWithDefaultNested extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $name,
                    public ?InnerDefaultData $address = null,
                ) {}
            }
        ');

        $reflection = new ReflectionMethod($this->strategy, 'getRouteValidationRules');
        $result = $reflection->invoke($this->strategy, 'OuterWithDefaultNested');

        expect($result)->toHaveKey('name');
        // The payload built by buildPayloadForNestedDataExpansion should include
        // the address key, causing Spatie to expand it
        expect($result)->toHaveKey('address.street');
        expect($result)->toHaveKey('address.city');
    });
});

describe('getCustomParameterData', function () {
    it('returns empty array when method does not exist', function () {
        $reflection = new ReflectionMethod($this->strategy, 'getCustomParameterData');
        $result = $reflection->invoke($this->strategy, 'stdClass');

        expect($result)->toBe([]);
    });

    it('calls customParameterData method when it exists', function () {
        $dataClass = new class extends Spatie\LaravelData\Data {
            public function __construct(public string $name = '')
            {
            }

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
    it('returns null when property does not exist on declaring class', function () {
        $class = new class {
            public function __construct(string $notAProperty = '')
            {
            }
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
            public function __construct(array $items = [])
            {
            }
        };
        $constructor = new ReflectionMethod($class, '__construct');
        $param = $constructor->getParameters()[0];

        $reflection = new ReflectionMethod($this->strategy, 'getDataCollectionOfClass');
        $result = $reflection->invoke($this->strategy, $param);

        expect($result)->toBeNull();
    });

    it('returns reflection for valid DataCollectionOf attribute', function () {
        $constructor = new ReflectionMethod(Tests\Fixtures\OrderData::class, '__construct');
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
        expect($result->getName())->toBe(Tests\Fixtures\NestedItemData::class);
    });
});

describe('buildNestedDataStub', function () {
    it('returns empty for class without constructor', function () {
        $class = new ReflectionClass(new class extends Spatie\LaravelData\Data {});

        $reflection = new ReflectionMethod($this->strategy, 'buildNestedDataStub');
        $result = $reflection->invoke($this->strategy, $class);

        expect($result)->toBe([]);
    });

    it('builds stub with null for builtin types', function () {
        $class = new ReflectionClass(Tests\Fixtures\NestedItemData::class);

        $reflection = new ReflectionMethod($this->strategy, 'buildNestedDataStub');
        $result = $reflection->invoke($this->strategy, $class);

        expect($result)->toHaveKey('sku');
        expect($result)->toHaveKey('qty');
        expect($result['sku'])->toBeNull();
    });

    it('builds nested stub for Data class properties', function () {
        $class = new ReflectionClass(Tests\Fixtures\OrderData::class);

        $reflection = new ReflectionMethod($this->strategy, 'buildNestedDataStub');
        $result = $reflection->invoke($this->strategy, $class);

        expect($result)->toHaveKey('customer_name');
        expect($result)->toHaveKey('main_item');
        expect($result['main_item'])->toBeArray();
        expect($result['main_item'])->toHaveKey('sku');
    });
});

describe('circular reference protection', function () {
    it('handles self-reference (A→A)', function () {
        eval('
            class SelfRefData extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $name,
                    public ?SelfRefData $parent = null,
                ) {}
            }
        ');

        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'SelfRefData');

        // parent should be expanded but its nested parent should be null (cycle broken)
        expect($result)->toHaveKey('parent');
        expect($result['parent'])->toBeArray();
        expect($result['parent'])->toHaveKey('name');
        expect($result['parent']['parent'])->toBeNull();
    });

    it('handles A→B→A cycle', function () {
        eval('
            class CycleB2 extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $label,
                    public ?CycleA2 $back = null,
                ) {}
            }
            class CycleA2 extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $name,
                    public ?CycleB2 $child = null,
                ) {}
            }
        ');

        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'CycleA2');

        // A.child = B expanded, B.back = A detected as cycle → null
        expect($result)->toHaveKey('child');
        expect($result['child'])->toBeArray();
        expect($result['child'])->toHaveKey('label');
        expect($result['child']['back'])->toBeNull();
    });

    it('handles A→B→C→A three-node cycle', function () {
        eval('
            class TriCycleC extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $value,
                    public ?TriCycleA $back_to_a = null,
                ) {}
            }
            class TriCycleB extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $value,
                    public ?TriCycleC $next = null,
                ) {}
            }
            class TriCycleA extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $value,
                    public ?TriCycleB $next = null,
                ) {}
            }
        ');

        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'TriCycleA');

        // A.next = B, B.next = C, C.back_to_a = null (cycle detected)
        expect($result['next'])->toBeArray();
        expect($result['next']['next'])->toBeArray();
        expect($result['next']['next']['back_to_a'])->toBeNull();
    });

    it('handles A→B→C→B cycle not involving root', function () {
        eval('
            class ChainC extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $value,
                    public ?ChainB2 $back = null,
                ) {}
            }
            class ChainB2 extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $value,
                    public ?ChainC $next = null,
                ) {}
            }
            class ChainA extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $value,
                    public ?ChainB2 $child = null,
                ) {}
            }
        ');

        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'ChainA');

        // A.child = B, B.next = C, C.back = null (B→C→B cycle detected)
        expect($result['child'])->toBeArray();
        expect($result['child']['next'])->toBeArray();
        expect($result['child']['next']['back'])->toBeNull();
    });

    it('handles diamond pattern (A→B,C; B→D; C→D) without false cycle detection', function () {
        eval('
            class DiamondD extends \Spatie\LaravelData\Data {
                public function __construct(public string $value) {}
            }
            class DiamondB extends \Spatie\LaravelData\Data {
                public function __construct(public DiamondD $d) {}
            }
            class DiamondC extends \Spatie\LaravelData\Data {
                public function __construct(public DiamondD $d) {}
            }
            class DiamondA extends \Spatie\LaravelData\Data {
                public function __construct(
                    public DiamondB $b,
                    public DiamondC $c,
                ) {}
            }
        ');

        $reflection = new ReflectionMethod($this->strategy, 'buildPayloadForNestedDataExpansion');
        $result = $reflection->invoke($this->strategy, 'DiamondA');

        // D should be fully expanded in BOTH paths (not falsely detected as cycle)
        expect($result['b'])->toBeArray();
        expect($result['b']['d'])->toBeArray();
        expect($result['b']['d']['value'])->toBeNull();

        expect($result['c'])->toBeArray();
        expect($result['c']['d'])->toBeArray();
        expect($result['c']['d']['value'])->toBeNull();
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
    it('returns empty when no Data parameter found', function () {
        $controller = new class {
            public function handle(string $name)
            {
            }
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'test', fn() => null);

        $endpoint = makeEndpointData([
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
            public function handle(Tests\Fixtures\EmptyData $data)
            {
            }
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'empty-data', fn() => null);

        $endpoint = makeEndpointData([
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

        $endpoint = makeEndpointData([
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

        $endpoint = makeEndpointData([
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

    it('extracts parameters from DataCollection typed property', function () {
        $controller = new class {
            public function handle(Tests\Fixtures\DataCollectionData $data)
            {
            }
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'data-collection', fn() => null);

        $endpoint = makeEndpointData([
            'route' => $route,
            'uri' => 'data-collection',
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
        expect($result)->toHaveKey('title');
        expect($result)->toHaveKey('items');
    });

    it('returns empty when strategy rejects the Data class', function () {
        $rejectingStrategy = new class (new DocumentationConfig(config('scribe') ?? [])) extends GetFromLaravelDataBase
        {
            protected string $customParameterDataMethodName = 'bodyParameters';

            protected function isLaravelDataMeantForThisStrategy(ReflectionClass $class): bool
            {
                return false;
            }
        };

        $controller = new class {
            public function handle(Tests\Fixtures\SimpleData $data)
            {
            }
        };
        $method = new ReflectionMethod($controller, 'handle');

        $route = new Route(['POST'], 'rejected', fn() => null);

        $endpoint = makeEndpointData([
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
