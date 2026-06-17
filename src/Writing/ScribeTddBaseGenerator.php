<?php

namespace AjCastro\ScribeTdd\Writing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\BaseGenerator;
use Symfony\Component\HttpFoundation\Response;

class ScribeTddBaseGenerator extends BaseGenerator
{
    protected function generateResponseContentSpec(?string $responseContent, OutputEndpointData $endpoint)
    {
        // Flatten array-valued headers to strings before generating spec,
        // since scribe-tdd stores headers in PSR-7 format (arrays)
        foreach ($endpoint->responses as $response) {
            foreach ($response->headers as $key => $value) {
                if (is_array($value)) {
                    $response->headers[$key] = $value[0] ?? '';
                }
            }
        }

        $result = parent::generateResponseContentSpec($responseContent, $endpoint);

        if (!isset($result['application/json']['schema'])) {
            return $result;
        }

        $schema = $result['application/json']['schema'];

        $isArraySchema = ($schema['type'] ?? '') === 'array';
        $schemaExample = $schema['example'] ?? null;
        $firstSchemaExample = is_array($schemaExample) ? $schemaExample[0] ?? null : null;

        $isExpandableArrayOfObjects =
            ($schema['items']['type'] ?? '') === 'object' &&
            empty($schema['items']['properties']) &&
            $firstSchemaExample instanceof \stdClass;

        $isExpandableArrayOfArrays =
            ($schema['items']['type'] ?? '') === 'array' &&
            empty($schema['items']['items']) &&
            is_array($firstSchemaExample) &&
            ($firstSchemaExample[0] ?? null) instanceof \stdClass;

        if ($isArraySchema && ($isExpandableArrayOfObjects || $isExpandableArrayOfArrays)) {
            $result['application/json']['schema']['items'] = $this->generateSchemaForResponseValue(
                $firstSchemaExample,
                $endpoint,
                ''
            );
        }

        return $result;
    }

    protected function generateEndpointParametersSpec(OutputEndpointData $endpoint): array
    {
        $parameters = parent::generateEndpointParametersSpec($endpoint);

        foreach ($parameters as &$param) {
            if (($param['in'] ?? '') !== 'query') {
                continue;
            }

            $name = $param['name'];
            $details = $endpoint->queryParameters[$name] ?? null;

            if ($details && str_contains($details['type'], '[]')) {
                $param['name'] = $name . '[]';
            }
        }

        return $parameters;
    }

    public function generateSchemaForResponseValue(mixed $value, OutputEndpointData $endpoint, string $path): array
    {
        $response = parent::generateSchemaForResponseValue($value, $endpoint, $path);

        if (($response['type'] ?? null) === 'integer' && is_int($value) && $value > 2 ** 32) {
            $response['format'] = 'int64';
        }

        return $response;
    }

    protected function generateEndpointResponsesSpec(OutputEndpointData $endpoint)
    {
        $responses = parent::generateEndpointResponsesSpec($endpoint);

        $operationId = $this->operationId($endpoint);

        foreach ($responses as $code => &$responseSpec) {
            if (!isset($responseSpec['content'])) {
                continue;
            }

            $phrase = (int) $code === 200 ? '' : $this->reasonPhrase((int) $code);

            foreach ($responseSpec['content'] as &$mediaType) {
                if (isset($mediaType['schema']) && is_array($mediaType['schema'])) {
                    $mediaType['schema']['title'] = $operationId . $phrase;
                }
            }
            unset($mediaType);
        }
        unset($responseSpec);

        return $responses;
    }

    protected function operationId(OutputEndpointData $endpoint): string
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() !== $endpoint->uri || empty(array_intersect($endpoint->httpMethods, $route->methods()))) {
                continue;
            }

            $uses = $route->getAction()['uses'] ?? null;
            if (!is_string($uses) || !str_contains($uses, '@')) {
                return $this->operationIdFromUri($endpoint);
            }

            $action = explode('@', $uses);
            $controllerName = last(explode('\\', $action[0]));
            $controllerName = Str::endsWith($controllerName, 'Controller')
                ? Str::beforeLast($controllerName, 'Controller')
                : $controllerName;
            $formattedAction = $controllerName . Str::ucfirst($action[1]);
            if (Str::contains($formattedAction, 'Batching')) {
                $tableName = last(explode('/', $endpoint->uri));
                $modelName = Str::studly($tableName);
                $formattedAction .= $modelName;
            }

            return $formattedAction;
        }

        return $this->operationIdFromUri($endpoint);
    }

    protected function reasonPhrase(int $code): string
    {
        $text = Response::$statusTexts[$code] ?? null;

        if ($text === null) {
            return (string) $code;
        }

        return Str::studly($text);
    }

    protected function operationIdFromUri(OutputEndpointData $endpoint): string
    {
        $method = mb_strtolower($endpoint->httpMethods[0] ?? 'get');
        $segments = array_filter(explode('/', $endpoint->uri));

        // Remove path parameter placeholders like {id}
        $segments = array_filter($segments, fn($s) => !str_starts_with($s, '{'));

        if (empty($segments)) {
            return $method;
        }

        return $method . Str::studly(implode(' ', $segments));
    }
}
