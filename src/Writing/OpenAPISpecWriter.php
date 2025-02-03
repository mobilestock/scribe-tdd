<?php

namespace AjCastro\ScribeTdd\Writing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenAPISpecWriter as WritingOpenAPISpecWriter;

class OpenAPISpecWriter extends WritingOpenAPISpecWriter
{
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
                                                return [$key => $this->generateSchemaForValue($value, $endpoint, $key)];
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
                        return [$key => $this->generateSchemaForValue($value, $endpoint, $key)];
                    })
                    ->toArray();
                $required = $this->filterRequiredFields($endpoint, array_keys($properties));

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
            if ($route->uri() !== $endpoint->uri) {
                continue;
            }

            $action = last(explode('@', $route->getAction()['uses']));
            return $action;
        }
        
        return parent::operationId($endpoint);
    }
}
