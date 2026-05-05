<?php

namespace AjCastro\ScribeTdd\Strategies;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\NestedRules;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\ConsoleOutputUtils;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

// @issue: https://github.com/mobilestock/backend/issues/1940
class GetFromLaravelDataBase extends Strategy
{
    use ParsesValidationRules;

    protected string $customParameterDataMethodName = '';

    private const HTTP_METHODS_WITHOUT_BODY = ['GET', 'HEAD', 'DELETE'];

    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        $this->endpointData = $endpointData;

        return $this->getParametersFromLaravelData($endpointData->method, $endpointData->route);
    }

    protected function isHttpMethodWithoutBody(): bool
    {
        $httpMethod = mb_strtoupper($this->endpointData->httpMethods[0]);

        $isMethodWithoutBody = in_array($httpMethod, self::HTTP_METHODS_WITHOUT_BODY);

        return $isMethodWithoutBody;
    }

    protected function getParametersFromLaravelData(ReflectionFunctionAbstract $method, $route): array
    {
        $laravelDataReflectionClass = $this->getLaravelDataReflectionClass($method);

        if (!$laravelDataReflectionClass) {
            return [];
        }

        if (!$this->isLaravelDataMeantForThisStrategy($laravelDataReflectionClass)) {
            return [];
        }

        $className = $laravelDataReflectionClass->getName();

        $rules = $this->getRouteValidationRules($className);

        if (empty($rules)) {
            return [];
        }

        $customParameterData = $this->getCustomParameterData($className);

        $parametersFromLaravelData = $this->getParametersFromValidationRules(
            $this->normalizeDataRules($rules),
            $customParameterData
        );

        return $this->normaliseArrayAndObjectParameters($parametersFromLaravelData);
    }

    protected function getRouteValidationRules(string $className): array
    {
        if (!method_exists($className, 'getValidationRules')) {
            return [];
        }

        $payload = $this->buildPayloadForNestedDataExpansion($className);
        $rules = $className::getValidationRules($payload);

        $rules = $this->expandNestedDataCollectionRules($className, $rules);

        return $rules;
    }

    protected function expandNestedDataCollectionRules(string $className, array $rules): array
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return $rules;
        }

        $parameters = $constructor->getParameters();
        foreach ($parameters as $param) {
            $collectionOf = $this->getDataCollectionOfClass($param);

            if (!$collectionOf) {
                continue;
            }

            $paramName = $param->getName();
            $fieldName = Str::snake($paramName);
            /** @var class-string<Data> $nestedClassName */
            $nestedClassName = $collectionOf->getName();

            $nestedPayload = $this->buildPayloadForNestedDataExpansion($nestedClassName);
            $nestedRules = $nestedClassName::getValidationRules($nestedPayload);

            foreach ($nestedRules as $nestedKey => $nestedRule) {
                $prefixedKey = "{$fieldName}.*.{$nestedKey}";

                if (!isset($rules[$prefixedKey])) {
                    $rules[$prefixedKey] = $nestedRule;
                }
            }
        }

        return $rules;
    }

    protected function buildPayloadForNestedDataExpansion(string $className): array
    {
        $payload = [];

        $reflection = new ReflectionClass($className);

        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            return $payload;
        }

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            $paramName = $param->getName();
            $fieldName = Str::snake($paramName);
            if ($type->isBuiltin()) {
                $collectionOf = $this->getDataCollectionOfClass($param);
                if ($collectionOf) {
                    $visited = [$className => true, $collectionOf->getName() => true];
                    $payload[$fieldName] = [$this->buildNestedDataStub($collectionOf, $visited)];
                } else {
                    $payload[$fieldName] = null;
                }
                continue;
            }

            $typeName = $type->getName();
            if (!class_exists($typeName)) {
                continue;
            }
            $typeReflection = new ReflectionClass($typeName);

            if ($typeReflection->isSubclassOf(Data::class)) {
                $visited = [$className => true, $typeName => true];
                $payload[$fieldName] = $this->buildNestedDataStub($typeReflection, $visited);
            } else {
                $collectionOf = $this->getDataCollectionOfClass($param);
                if ($collectionOf) {
                    $visited = [$className => true, $collectionOf->getName() => true];
                    $payload[$fieldName] = [$this->buildNestedDataStub($collectionOf, $visited)];
                } else {
                    $payload[$fieldName] = null;
                }
            }
        }

        return $payload;
    }

    protected function buildNestedDataStub(ReflectionClass $dataClass, array $visited = []): array
    {
        $stub = [];

        $constructor = $dataClass->getConstructor();
        if (!$constructor) {
            return $stub;
        }

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && class_exists($type->getName())) {
                $typeReflection = new ReflectionClass($type->getName());

                if ($typeReflection->isSubclassOf(Data::class) && !isset($visited[$type->getName()])) {
                    $stub[Str::snake($param->getName())] = $this->buildNestedDataStub($typeReflection, [
                        ...$visited,
                        $type->getName() => true,
                    ]);
                    continue;
                }
            }

            $collectionOf = $this->getDataCollectionOfClass($param);
            if ($collectionOf && !isset($visited[$collectionOf->getName()])) {
                $stub[Str::snake($param->getName())] = [
                    $this->buildNestedDataStub($collectionOf, [...$visited, $collectionOf->getName() => true]),
                ];
                continue;
            }

            $stub[Str::snake($param->getName())] = null;
        }

        return $stub;
    }

    protected function getDataCollectionOfClass(ReflectionParameter $param): ?ReflectionClass
    {
        /** @var \ReflectionMethod $declaringFunction */
        $declaringFunction = $param->getDeclaringFunction();
        $declaringClass = $declaringFunction->getDeclaringClass();
        if (!$declaringClass->hasProperty($param->getName())) {
            return null;
        }

        $property = $declaringClass->getProperty($param->getName());
        $attributes = $property->getAttributes(DataCollectionOf::class);
        if (empty($attributes)) {
            return null;
        }

        $className = $attributes[0]->newInstance()->class;
        $reflection = new ReflectionClass($className);

        return $reflection->isSubclassOf(Data::class) ? $reflection : null;
    }

    protected function getCustomParameterData(string $className): array
    {
        if (method_exists($className, $this->customParameterDataMethodName)) {
            return $className::{$this->customParameterDataMethodName}();
        }

        ConsoleOutputUtils::debug(
            "No {$this->customParameterDataMethodName}() method found in {$className}. Scribe will only be able to extract basic information from the validation rules."
        );

        return [];
    }

    protected function getMissingCustomDataMessage($parameterName): string
    {
        return "No data found for parameter '{$parameterName}' in your {$this->customParameterDataMethodName}() method. Add an entry for '{$parameterName}' so you can add a description and example.";
    }

    protected function getLaravelDataReflectionClass(ReflectionFunctionAbstract $method): ?ReflectionClass
    {
        foreach ($method->getParameters() as $argument) {
            $argType = $argument->getType();

            if ($argType === null || $argType instanceof ReflectionUnionType) {
                continue;
            }

            $argumentClassName = $argType->getName();

            if (!class_exists($argumentClassName)) {
                continue;
            }

            $argumentClass = new ReflectionClass($argumentClassName);

            if ($argumentClass->isSubclassOf(Data::class)) {
                return $argumentClass;
            }
        }

        return null;
    }

    protected function isLaravelDataMeantForThisStrategy(ReflectionClass $laravelDataReflectionClass): bool
    {
        return true;
    }

    /**
     * Normalize rules returned by laravel-data's getValidationRules()
     * to ensure compatibility with Scribe's ParsesValidationRules.
     *
     * Rules may contain Spatie validation attribute objects that need
     * to be converted to strings or Laravel Rule objects.
     */
    protected function normalizeDataRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $fieldRules) {
            $field = preg_replace('/\.(\d+)(\.|\z)/', '.*$2', $field);

            if (is_string($fieldRules)) {
                $normalized[$field] = $fieldRules;
                continue;
            }

            if ($fieldRules instanceof NestedRules) {
                continue;
            }

            if (!is_array($fieldRules)) {
                $normalized[$field] = [$fieldRules];
                continue;
            }

            $normalizedFieldRules = [];

            foreach ($fieldRules as $rule) {
                if (
                    is_string($rule) ||
                    $rule instanceof EnumRule ||
                    $rule instanceof Rule ||
                    $rule instanceof ValidationRule
                ) {
                    $normalizedFieldRules[] = $rule;
                } elseif (is_object($rule) && method_exists($rule, '__toString')) {
                    $normalizedFieldRules[] = (string) $rule;
                } else {
                    // Laravel Rule objects, custom rules, etc. - pass through as-is
                    // Scribe's ParsesValidationRules handles these natively
                    $normalizedFieldRules[] = $rule;
                }
            }

            $normalized[$field] = $normalizedFieldRules;
        }

        return $normalized;
    }
}
