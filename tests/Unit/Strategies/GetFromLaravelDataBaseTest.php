<?php

use AjCastro\ScribeTdd\Strategies\GetFromLaravelDataBase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\NestedRules;
use Illuminate\Validation\Rules\Enum;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Spatie\LaravelData\Data;
use Tests\Fixtures\DataCollectionData;
use Tests\Fixtures\DeepNestedData;
use Tests\Fixtures\EmptyData;
use Tests\Fixtures\FilterItemData;
use Tests\Fixtures\MixedParamData;
use Tests\Fixtures\NestedItemData;
use Tests\Fixtures\OrderData;
use Tests\Fixtures\ParentWithFiltersData;
use Tests\Fixtures\SimpleData;
use Tests\Fixtures\TestFilterType;

beforeEach(function () {
    $this->strategy = new class (new DocumentationConfig(config('scribe') ?? [])) extends GetFromLaravelDataBase {
        protected string $customParameterDataMethodName = 'bodyParameters';
    };
});

function invokeStrategy(object $strategy, string $method, mixed ...$args): mixed
{
    $result = (new ReflectionMethod($strategy, $method))->invoke($strategy, ...$args);

    return $result;
}

function makeEndpointForController(object $controller, string $methodName, string $uri): ExtractedEndpointData
{
    $endpoint = makeEndpointData([
        'route' => new Route(['POST'], $uri, fn() => null),
        'uri' => $uri,
        'httpMethods' => ['POST'],
        'method' => new ReflectionMethod($controller, $methodName),
    ]);

    return $endpoint;
}

describe('getLaravelDataReflectionClass', function () {
    it('returns null when no Data class parameter found', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle()
                {
                }
            },
            'handle'
        );

        $result = invokeStrategy($this->strategy, 'getLaravelDataReflectionClass', $method);

        expect($result)->toBeNull();
    });

    it('returns null for Data base class itself (not a subclass)', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle()
                {
                }
            },
            'handle'
        );

        $result = invokeStrategy($this->strategy, 'getLaravelDataReflectionClass', $method);

        expect($result)->toBeNull();
    });

    it('returns reflection for Data subclass', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle(SimpleData $data)
                {
                }
            },
            'handle'
        );
        $result = invokeStrategy($this->strategy, 'getLaravelDataReflectionClass', $method);

        expect($result)->toBeInstanceOf(ReflectionClass::class);
        expect($result->getName())->toBe(SimpleData::class);
    });

    it('skips union type parameters', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle()
                {
                }
            },
            'handle'
        );

        $result = invokeStrategy($this->strategy, 'getLaravelDataReflectionClass', $method);

        expect($result)->toBeNull();
    });

    it('skips parameters without type hint', function () {
        $method = new ReflectionMethod(
            new class {
                public function handle($data)
                {
                }
            },
            'handle'
        );

        $result = invokeStrategy($this->strategy, 'getLaravelDataReflectionClass', $method);

        expect($result)->toBeNull();
    });
});

describe('normalizeDataRules', function () {
    it('passes through string rules', function () {
        $result = invokeStrategy($this->strategy, 'normalizeDataRules', ['name' => 'required|string']);

        expect($result)->toBe(['name' => 'required|string']);
    });

    it('converts numeric indices to wildcards', function () {
        $result = invokeStrategy($this->strategy, 'normalizeDataRules', [
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

        $result = invokeStrategy($this->strategy, 'normalizeDataRules', ['field' => [$rule]]);

        expect($result['field'])->toBe(['required|string']);
    });

    it('passes through non-array non-string rules as array', function () {
        $rule = new stdClass();

        $result = invokeStrategy($this->strategy, 'normalizeDataRules', ['field' => $rule]);

        expect($result['field'])->toBe([$rule]);
    });

    it('passes through Laravel Rule objects in arrays', function () {
        $rule = new class implements ValidationRule {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
            }
        };

        $result = invokeStrategy($this->strategy, 'normalizeDataRules', ['field' => ['required', $rule]]);

        expect($result['field'][0])->toBe('required');
        expect($result['field'][1])->toBe($rule);
    });

    it('converts numeric index at end of field path', function () {
        $result = invokeStrategy($this->strategy, 'normalizeDataRules', [
            'items.0' => 'required|integer',
        ]);

        expect($result)->toHaveKey('items.*');
    });

    it('should skip NestedRules instances', function () {
        $nestedRules = new NestedRules(fn() => ['required', 'string']);

        $result = invokeStrategy($this->strategy, 'normalizeDataRules', [
            'name' => 'required|string',
            'items.*' => $nestedRules,
        ]);

        expect($result)->toHaveKey('name');
        expect($result)->not->toHaveKey('items.*');
    });

    it('should preserve Enum rule objects instead of converting to string', function () {
        $enumRule = new Enum(TestFilterType::class);

        $result = invokeStrategy($this->strategy, 'normalizeDataRules', [
            'type' => ['nullable', $enumRule],
        ]);

        expect($result['type'][0])->toBe('nullable');
        expect($result['type'][1])->toBeInstanceOf(Enum::class);
    });

    it('should pass through objects without __toString that are not Rule instances', function () {
        $rule = new stdClass();

        $result = invokeStrategy($this->strategy, 'normalizeDataRules', [
            'field' => ['required', $rule],
        ]);

        expect($result['field'][0])->toBe('required');
        expect($result['field'][1])->toBe($rule);
    });
});

describe('buildPayloadForNestedDataExpansion', function () {
    it('returns empty array for class without constructor', function () {
        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', 'stdClass');

        expect($result)->toBe([]);
    });

    it('builds payload for nested Data class parameters', function () {
        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', OrderData::class);

        expect($result)->toHaveKey('main_item');
        expect($result['main_item'])->toBeArray();
        expect($result['main_item'])->toHaveKey('sku');
        expect($result['main_item'])->toHaveKey('qty');
    });

    it('builds payload for DataCollectionOf attributes', function () {
        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', OrderData::class);

        expect($result)->toHaveKey('items');
        expect($result['items'])->toBeArray();
        expect($result['items'][0])->toHaveKey('sku');
    });

    it('builds payload for DataCollectionOf with DataCollection type', function () {
        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', DataCollectionData::class);

        expect($result)->toHaveKey('items');
        expect($result['items'])->toBeArray();
        expect($result['items'][0])->toHaveKey('sku');
        expect($result['items'][0])->toHaveKey('qty');
    });

    it('includes builtin types without DataCollectionOf as null', function () {
        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', SimpleData::class);

        expect($result)->toBe(['name' => null, 'quantity' => null]);
    });

    it('should include enum typed parameters as null in payload', function () {
        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', FilterItemData::class);

        expect($result)->toHaveKey('type');
        expect($result['type'])->toBeNull();
    });

    it('includes builtin types and skips union type parameters', function () {
        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', MixedParamData::class);

        expect($result)->toBe(['name' => null]);
    });

    it('includes builtin types and skips non-existent class type hints', function () {
        eval('
            class DataWithNonExistentTypeHint extends Spatie\LaravelData\Data {
                public function __construct(
                    public string $name,
                    public \NonExistent\SomeClass $thing,
                ) {}
            }
        ');

        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', 'DataWithNonExistentTypeHint');

        expect($result)->toBe(['name' => null]);
    });
});

describe('getRouteValidationRules', function () {
    it('returns empty array for class without getValidationRules', function () {
        $result = invokeStrategy($this->strategy, 'getRouteValidationRules', 'stdClass');

        expect($result)->toBe([]);
    });

    it('returns validation rules for Data class', function () {
        $result = invokeStrategy($this->strategy, 'getRouteValidationRules', SimpleData::class);

        expect($result)->toBeArray();
        expect($result)->not->toBeEmpty();
    });

    it('returns rules for builtin properties with defaults when payload includes their keys', function () {
        eval('
            class AllDefaultsData extends \Spatie\LaravelData\Data {
                public function __construct(
                    public string $name = "default",
                    public int $quantity = 0,
                    public ?string $notes = null,
                ) {}
            }
        ');

        $result = invokeStrategy($this->strategy, 'getRouteValidationRules', 'AllDefaultsData');

        expect($result)->toHaveKey('name');
        expect($result)->toHaveKey('quantity');
        expect($result)->toHaveKey('notes');
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

        $result = invokeStrategy($this->strategy, 'getRouteValidationRules', 'OuterWithDefaultNested');

        expect($result)->toHaveKey('name');
        expect($result)->toHaveKey('address.street');
        expect($result)->toHaveKey('address.city');
    });

    it('should include nested DataCollectionOf DTO rules in expanded rules', function () {
        $result = invokeStrategy($this->strategy, 'getRouteValidationRules', ParentWithFiltersData::class);

        expect($result)->toHaveKey('filters.*.tags');
        expect($result)->toHaveKey('filters.*.type');
        expect($result)->toHaveKey('filters.*.tags.*');
    });
});

describe('getCustomParameterData', function () {
    it('returns empty array when method does not exist', function () {
        $result = invokeStrategy($this->strategy, 'getCustomParameterData', 'stdClass');

        expect($result)->toBe([]);
    });

    it('calls customParameterData method when it exists', function () {
        $dataClass = new class extends Data {
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

        $result = invokeStrategy($this->strategy, 'getCustomParameterData', get_class($dataClass));

        expect($result)->toHaveKey('name');
        expect($result['name']['description'])->toBe('The name');
    });
});

describe('getMissingCustomDataMessage', function () {
    it('includes parameter name and method name in message', function () {
        $result = invokeStrategy($this->strategy, 'getMissingCustomDataMessage', 'name');

        expect($result)->toContain('name');
        expect($result)->toContain('bodyParameters');
    });
});

describe('getDataCollectionOfClass', function () {
    it('returns null when property does not exist on declaring class', function () {
        $class = new class {
            public function __construct(string $notAProperty = '')
            {
            }
        };
        $param = (new ReflectionMethod($class, '__construct'))->getParameters()[0];

        $result = invokeStrategy($this->strategy, 'getDataCollectionOfClass', $param);

        expect($result)->toBeNull();
    });

    it('returns null when no DataCollectionOf attribute', function () {
        $class = new class {
            public array $items;
            public function __construct(array $items = [])
            {
            }
        };
        $param = (new ReflectionMethod($class, '__construct'))->getParameters()[0];

        $result = invokeStrategy($this->strategy, 'getDataCollectionOfClass', $param);

        expect($result)->toBeNull();
    });

    it('returns reflection for valid DataCollectionOf attribute', function () {
        $constructor = new ReflectionMethod(OrderData::class, '__construct');
        $itemsParam = null;
        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === 'items') {
                $itemsParam = $param;
                break;
            }
        }

        $result = invokeStrategy($this->strategy, 'getDataCollectionOfClass', $itemsParam);

        expect($result)->toBeInstanceOf(ReflectionClass::class);
        expect($result->getName())->toBe(NestedItemData::class);
    });
});

describe('buildNestedDataStub', function () {
    it('returns empty for class without constructor', function () {
        $class = new ReflectionClass(new class extends Data {});

        $result = invokeStrategy($this->strategy, 'buildNestedDataStub', $class);

        expect($result)->toBe([]);
    });

    it('builds stub with null for builtin types', function () {
        $result = invokeStrategy($this->strategy, 'buildNestedDataStub', new ReflectionClass(NestedItemData::class));

        expect($result)->toHaveKey('sku');
        expect($result)->toHaveKey('qty');
        expect($result['sku'])->toBeNull();
    });

    it('builds nested stub for Data class properties', function () {
        $result = invokeStrategy($this->strategy, 'buildNestedDataStub', new ReflectionClass(OrderData::class));

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

        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', 'SelfRefData');

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

        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', 'CycleA2');

        expect($result)->toHaveKey('child');
        expect($result['child'])->toBeArray();
        expect($result['child'])->toHaveKey('label');
        expect($result['child']['back'])->toBeNull();
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

        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', 'ChainA');

        expect($result['child'])->toBeArray();
        expect($result['child']['next'])->toBeArray();
        expect($result['child']['next']['back'])->toBeNull();
    });

    it('handles diamond pattern without false cycle detection', function () {
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

        $result = invokeStrategy($this->strategy, 'buildPayloadForNestedDataExpansion', 'DiamondA');

        expect($result['b']['d']['value'])->toBeNull();
        expect($result['c']['d']['value'])->toBeNull();
    });
});

describe('__invoke', function () {
    it('returns empty when no Data parameter found', function () {
        $controller = new class {
            public function handle(string $name)
            {
            }
        };
        $endpoint = makeEndpointForController($controller, 'handle', 'test');

        $result = $this->strategy->__invoke($endpoint);

        expect($result)->toBe([]);
    });

    it('returns empty when Data class has no validation rules', function () {
        $controller = new class {
            public function handle(EmptyData $data)
            {
            }
        };
        $endpoint = makeEndpointForController($controller, 'handle', 'empty-data');

        $result = $this->strategy->__invoke($endpoint);

        expect($result)->toBe([]);
    });

    it('extracts parameters from Data class', function () {
        $controller = new class {
            public function handle(SimpleData $data)
            {
            }
        };
        $endpoint = makeEndpointForController($controller, 'handle', 'simple-data');
        $result = $this->strategy->__invoke($endpoint);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('name');
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
            public function handle(SimpleData $data)
            {
            }
        };
        $endpoint = makeEndpointForController($controller, 'handle', 'rejected');

        $result = $rejectingStrategy->__invoke($endpoint);

        expect($result)->toBe([]);
    });

    it('should extract nested DataCollectionOf parameters with enum fields', function () {
        $controller = new class {
            public function handle(ParentWithFiltersData $data)
            {
            }
        };
        $endpoint = makeEndpointForController($controller, 'handle', 'parent-filters');

        $result = $this->strategy->__invoke($endpoint);

        expect($result)->toHaveKey('filters');
        expect($result['filters']['type'])->toBe('object[]');
        expect($result)->toHaveKey('filters[].tags');
        expect($result)->toHaveKey('filters[].type');
        expect($result['filters[].type']['type'])->toBe('string');
    });
});

describe('expandNestedDataCollectionRules', function () {
    it('should return rules unchanged when no DataCollectionOf exists', function () {
        $rules = ['name' => ['required', 'string'], 'quantity' => ['required', 'integer']];

        $result = invokeStrategy($this->strategy, 'expandNestedDataCollectionRules', SimpleData::class, $rules);

        expect($result)->toBe($rules);
    });

    it('should return rules unchanged when class has no constructor', function () {
        $rules = ['field' => ['required']];

        $result = invokeStrategy($this->strategy, 'expandNestedDataCollectionRules', 'stdClass', $rules);

        expect($result)->toBe($rules);
    });

    it('should expand nested DTO rules with field prefix', function () {
        $rules = ['customer_name' => ['required', 'string'], 'items' => ['array']];

        $result = invokeStrategy($this->strategy, 'expandNestedDataCollectionRules', OrderData::class, $rules);

        expect($result)->toHaveKey('items.*.sku');
        expect($result)->toHaveKey('items.*.qty');
    });

    it('should not overwrite existing prefixed rules', function () {
        $customRule = ['required', 'string', 'max:100'];
        $rules = ['customer_name' => ['required', 'string'], 'items' => ['array'], 'items.*.sku' => $customRule];

        $result = invokeStrategy($this->strategy, 'expandNestedDataCollectionRules', OrderData::class, $rules);

        expect($result['items.*.sku'])->toBe($customRule);
    });

    it('should expand rules for DTO with enum fields', function () {
        $rules = ['name' => ['required', 'string'], 'filters' => ['array']];

        $result = invokeStrategy(
            $this->strategy,
            'expandNestedDataCollectionRules',
            ParentWithFiltersData::class,
            $rules
        );

        expect($result)->toHaveKey('filters.*.tags');
        expect($result)->toHaveKey('filters.*.type');
        expect($result)->toHaveKey('filters.*.tags.*');
    });

    it('should expand multi-level nested rules', function () {
        $rules = ['title' => ['required', 'string'], 'sections' => ['array']];

        $result = invokeStrategy($this->strategy, 'expandNestedDataCollectionRules', DeepNestedData::class, $rules);

        expect($result)->toHaveKey('sections.*.label');
        expect($result)->toHaveKey('sections.*.detail');
    });
});
