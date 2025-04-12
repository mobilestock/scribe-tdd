<?php

namespace AjCastro\ScribeTdd\Writing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenAPISpecWriter as WritingOpenAPISpecWriter;
use Knuckles\Camel\Output\Parameter;

class OpenAPISpecWriter extends WritingOpenAPISpecWriter
{
    protected function generateEndpointParametersSpec(OutputEndpointData $endpoint): array
    {
        $parameters = [];

        if (count($endpoint->queryParameters)) {
            /**
             * @var string $name
             * @var Parameter $details
             */
            foreach ($endpoint->queryParameters as $name => $details) {
                $parameterData = [
                    'in' => 'query',
                    'name' => $name . (str_contains($details['type'], '[]') ? '[]' : ''),
                    'description' => $details->description,
                    'example' => $details->example,
                    'required' => $details->required,
                    'schema' => $this->generateFieldData($details),
                ];
                $parameters[] = $parameterData;
            }
        }

        if (count($endpoint->headers)) {
            foreach ($endpoint->headers as $name => $value) {
                if (in_array(mb_strtolower($name), ['content-type', 'accept', 'authorization'])) {
                    // These headers are not allowed in the spec.
                    // https://swagger.io/docs/specification/describing-parameters/#header-parameters
                    continue;
                }

                $parameters[] = [
                    'in' => 'header',
                    'name' => $name,
                    'description' => '',
                    'example' => $value,
                    'schema' => [
                        'type' => 'string',
                    ],
                ];
            }
        }

        return $parameters;
    }

    protected function generateResponseContentSpec(?string $responseContent, OutputEndpointData $endpoint)
    {
        if (Str::startsWith($responseContent, '<<binary>>')) {
            return [
                'application/octet-stream' => [
                    'schema' => [
                        'type' => 'string',
                        'format' => 'binary',
                    ],
                ],
            ];
        }

        if ($responseContent === null) {
            return [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        // See https://swagger.io/docs/specification/data-models/data-types/#null
                        'nullable' => true,
                    ],
                ],
            ];
        }

        $decoded = json_decode($responseContent);
        if ($decoded === null) {
            // Decoding failed, so we return the content string as is
            return [
                'text/plain' => [
                    'schema' => [
                        'type' => 'string',
                        'example' => $responseContent,
                    ],
                ],
            ];
        }

        switch ($type = gettype($decoded)) {
            case 'string':
            case 'boolean':
            case 'integer':
            case 'double':
                return [
                    'application/json' => [
                        'schema' => [
                            'type' => $type === 'double' ? 'number' : $type,
                            'example' => $decoded,
                        ],
                    ],
                ];

            case 'array':
                if (!count($decoded)) {
                    // empty array
                    return [
                        'application/json' => [
                            'schema' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object', // No better idea what to put here
                                ],
                                'example' => $decoded,
                            ],
                        ],
                    ];
                }

                $childrenType = $this->convertScribeOrPHPTypeToOpenAPIType(gettype($decoded[0]));
                // Non-empty array
                return [
                    'application/json' => [
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => $childrenType,
                                'properties' => match ($childrenType) {
                                    'object' => $this->objectIfEmpty(
                                        collect($decoded[0])
                                            ->mapWithKeys(function ($value, $key) use ($endpoint) {
                                                return [
                                                    $key => $this->generateSchemaForResponseValue(
                                                        $value,
                                                        $endpoint,
                                                        $key
                                                    ),
                                                ];
                                            })
                                            ->toArray()
                                    ),
                                    default => [],
                                },
                            ],
                            'example' => $decoded,
                        ],
                    ],
                ];

            case 'object':
                $properties = collect($decoded)
                    ->mapWithKeys(function ($value, $key) use ($endpoint) {
                        return [$key => $this->generateSchemaForResponseValue($value, $endpoint, $key)];
                    })
                    ->toArray();
                $required = $this->filterRequiredResponseFields($endpoint, array_keys($properties));

                $data = [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'example' => $decoded,
                            'properties' => $this->objectIfEmpty($properties),
                        ],
                    ],
                ];
                if ($required) {
                    $data['application/json']['schema']['required'] = $required;
                }

                return $data;
        }
    }

    protected function operationId(OutputEndpointData $endpoint): string
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() !== $endpoint->uri || empty(array_intersect($endpoint->httpMethods, $route->methods()))) {
                continue;
            }

            $action = explode('@', $route->getAction()['uses']);
            $formattedAction = last(explode('\\', $action[0])) . Str::ucfirst($action[1]);
            if (str_contains($formattedAction, 'Batching')) {
                $tableName = last(explode('/', $endpoint->uri));
                $modelName = Str::singular(Str::studly($tableName));
                $formattedAction .= $modelName;
            }

            return $formattedAction;
        }

        return parent::operationId($endpoint);
    }
}
