<?php

declare(strict_types=1);

namespace ConfluenceApiDoc;

use ConfluenceApiDoc\Exception\OpenApiProviderException;
use Symfony\Component\Yaml\Yaml;

class OpenApiProvider
{
    use OpenApiUtilsTrait;

    private array $openapi;

    /**
     * @param string $path
     *
     * @throws OpenApiProviderException
     */
    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw OpenApiProviderException::missingOpenApiFile($path);
        }
        if (!is_readable($path)) {
            throw OpenApiProviderException::notReadableOpenApiFile($path);
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array($ext, ['yaml', 'yml'], true)) {
            $data = Yaml::parseFile($path);
        } elseif ('json' === $ext) {
            $data = json_decode(file_get_contents($path), true);
        } else {
            throw OpenApiProviderException::unsupportedOpenApiFileType($path, ['yaml', 'yml', 'json']);
        }

        if (!$data || !is_array($data)) {
            throw OpenApiProviderException::corruptedOpenApiFile($path);
        }

        $this->openapi = $data;
    }

    public function getEndpoints(): array
    {
        $endpoints = [];
        $pathPrefix = $this->getPathPrefix();

        foreach ($this->getPaths() as $pathUri => $path) {
            foreach ($path as $methodType => $method) {
                $endpoints[] = $this->createEndpoint($pathUri, $pathPrefix, $methodType, $method);
            }
        }

        return $endpoints;
    }

    private function createEndpoint(string $pathUri, string $pathPrefix, string $methodType, array $method): array
    {
        $endpoint = [
            'path' => $pathUri,
            'pathPrefix' => $pathPrefix,
            'method' => strtoupper($methodType),
            'summary' => '',
            'description' => '',
            'requestPathParameters' => [],
            'requestQueryParameters' => [],
            'requestBodies' => [],
            'responses' => [],
        ];

        if (!empty($method['summary'])) {
            $endpoint['summary'] = $method['summary'];
        }

        if (!empty($method['description'])) {
            $endpoint['description'] = $method['description'];
        }

        if (!empty($method['parameters'])) {
            $endpoint['requestPathParameters'] = $this->filterParameters($method['parameters'], 'path');
            $endpoint['requestQueryParameters'] = $this->filterParameters($method['parameters'], 'query');
        }

        if (!empty($method['requestBody']['content'])) {
            $endpoint['requestBodies'] = $this->createRequestBodies($method['requestBody']['content']);
        }

        if (!empty($method['responses'])) {
            $endpoint['responses'] = $this->createResponses($method['responses']);
        }

        $endpoint['requestHeaders'] = $this->createRequestHeaders(
            $endpoint['requestBodies'],
            $endpoint['responses'],
            $method['security'] ?? [],
        );

        $endpoint['requestCookies'] = $this->createRequestCookies($method['security'] ?? []);

        return $endpoint;
    }

    private function filterParameters(array $parameters, string $in): array
    {
        $filtered = [];
        foreach ($parameters as $parameter) {
            if ($in === $parameter['in']) {
                $filtered[] = $parameter;
            }
        }

        return $filtered;
    }

    private function createRequestBodies(array $contents): array
    {
        $requestBodies = [];

        foreach ($contents as $contentType => $content) {
            $request = [
                'contentType' => $contentType,
                ...$this->createContentSchemaInfo($content),
            ];
            $requestBodies[] = $request;
        }

        return $requestBodies;
    }

    private function createResponses(array $data): array
    {
        $responses = [];

        foreach ($data as $responseCode => $responseData) {
            $responses[$responseCode] = [
                'description' => $responseData['description'],
                'content' => [],
            ];
            foreach ($responseData['content'] ?? [] as $contentType => $content) {
                $responses[$responseCode]['content'][$contentType] = $this->createContentSchemaInfo($content);
            }
        }

        return $responses;
    }

    private function createContentSchemaInfo(array $content): array
    {
        if (empty($content['schema'])) {
            return [];
        }

        $schema = $this->getNormalizedSchema($content['schema']);
        $isArray = 'array' === ($content['schema']['type'] ?? '');
        $example = null;
        $examples = null;

        if (!empty($content['example'])) {
            $example = $content['example'];
        } elseif (!empty($schema['example'])) {
            $example = $isArray ? [$schema['example']] : $schema['example'];
        }

        if (!empty($content['examples'])) {
            $examples = $content['examples'];
        }

        return [
            'schema' => $schema,
            'schemaName' => $this->getSchemaNameBySchema($content['schema']),
            'isArray' => $isArray,
            'example' => $example,
            'examples' => $examples,
        ];
    }

    private function createRequestHeaders(array $requestBodies, array $responses, array $security): array
    {
        $headers = [];

        // Set the Accept header value based on the first content type of the first available response
        foreach ($responses as $response) {
            foreach (array_keys($response['content']) as $contentType) {
                $headers['Accept'] = $contentType;
                break;
            }
            break;
        }

        // Set the Content-Type header value based on the content type of the first available request body
        foreach ($requestBodies as $requestBody) {
            if (!empty($requestBody['contentType'])) {
                $headers['Content-Type'] = $requestBody['contentType'];
            }
            break;
        }

        // Add all required security headers with a placeholder as a value
        foreach ($this->getMethodSecurityByType($security, 'header') as $securitySchema) {
            $headers[$securitySchema['name']] = '...';
        }

        return $headers;
    }

    private function createRequestCookies(array $security): array
    {
        $cookies = [];

        // Add all required security cookies with a placeholder as a value
        foreach ($this->getMethodSecurityByType($security, 'cookie') as $securitySchema) {
            $cookies[$securitySchema['name']] = '...';
        }

        return $cookies;
    }

    public function getOpenApi(): array
    {
        return $this->openapi;
    }

    public function getVersion(): string
    {
        return (string) ($this->openapi['openapi'] ?? '');
    }

    public function getPathPrefix(): string
    {
        return (string) ($this->openapi['servers'][0]['url'] ?? '');
    }

    public function getPaths(): array
    {
        return $this->openapi['paths'] ?? [];
    }

    public function getSchemas(): array
    {
        $schemas = [];
        foreach ($this->openapi['components']['schemas'] ?? [] as $schemaName => $schema) {
            $schemas[$schemaName] = $this->normalizeSchema($schema);
        }

        return $schemas;
    }

    public function getSecuritySchemes(): array
    {
        return $this->openapi['components']['securitySchemes'] ?? [];
    }

    public function getSchemaNameBySchema(array $schema): string
    {
        $ref = '';
        if (!empty($schema['$ref'])) {
            $ref = $schema['$ref'];
        } elseif ('array' === ($schema['type'] ?? '') && !empty($schema['items']['$ref'])) {
            $ref = $schema['items']['$ref'];
        }

        return $ref ? $this->getSchemaNameByRef($ref) : '';
    }

    public function getSchemaByName(string $name): ?array
    {
        return $this->openapi['components']['schemas'][$name] ?? null;
    }

    private function getNormalizedSchema(array $schema): array
    {
        if (!empty($schema['$ref'])) {
            $name = $this->getSchemaNameByRef($schema['$ref']);
            $schema = $this->getSchemaByName($name);
        } elseif ('array' === ($schema['type'] ?? '') && !empty($schema['items']['$ref'])) {
            $name = $this->getSchemaNameByRef($schema['items']['$ref']);
            $schema = $this->getSchemaByName($name);
        }

        return $this->normalizeSchema($schema);
    }

    private function normalizeSchema(array $schema): array
    {
        $allOf = $schema['allOf'] ?? [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        if ($allOf) {
            foreach ($allOf as $subSchema) {
                if (isset($subSchema['$ref'])) {
                    $subSchema = $this->getSchemaByName($this->getSchemaNameByRef($subSchema['$ref']));
                }
                $properties = [...$properties, ...($subSchema['properties'] ?? [])];
                $required = [...$required, ...($subSchema['required'] ?? [])];
            }
            unset($schema['allOf']);
            $schema['required'] = $required;
        }

        foreach ($properties as $propertyName => $property) {
            $properties[$propertyName] = $this->normalizeProperty($property);
        }

        $schema['properties'] = $properties;

        if ($schema['properties']) {
            $schema['type'] = 'object';
        }

        return $schema;
    }

    private function normalizeProperty(array $property): array
    {
        if (isset($property['$ref'])) {
            $name = $this->getSchemaNameByRef($property['$ref']);
            $schema = $this->getSchemaByName($name);
            if (empty($property['description'])) {
                $property['description'] = $schema['description'] ?? '';
            }
        }

        return $property;
    }

    private function getMethodSecurityByType(array $methodSecurity, string $type): array
    {
        $schemesWithType = [];
        foreach ($this->getSecuritySchemes() as $schemeName => $scheme) {
            if ($type === ($scheme['in'] ?? '')) {
                $schemesWithType[$schemeName] = $scheme;
            }
        }

        $methodSecuritySchemas = [];
        foreach ($methodSecurity as $methodSecurityItems) {
            foreach (array_keys($methodSecurityItems) as $methodSecuritySchemaName) {
                if (isset($schemesWithType[$methodSecuritySchemaName])) {
                    $methodSecuritySchemas[$methodSecuritySchemaName] = $schemesWithType[$methodSecuritySchemaName];
                }
            }
        }

        return $methodSecuritySchemas;
    }
}
