<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Generator;

use DH\Adf\Node\Block\{
    CodeBlock,
    Document,
    Heading,
    Panel,
    Paragraph,
    Table,
};
use DH\Adf\Node\Child\{
    TableCell,
    TableHeader,
    TableRow,
};
use DH\Adf\Node\Inline\{
    Extension,
    Status,
    Text
};
use DH\Adf\Node\Mark\{
    Code,
    Link,
    Strong,
};
use DH\Adf\Node\Node;
use ConfluenceApiDoc\OpenApiProvider;
use ConfluenceApiDoc\OpenApiUtilsTrait;

class DefaultGenerator implements DocumentGeneratorInterface
{
    use MarkdownTrait;
    use OpenApiUtilsTrait;
    use PropertyTrait;

    public function generate(OpenApiProvider $api): string
    {
        $document = new Document();
        $document->append($this->createTableOfContent());

        foreach ($api->getEndpoints() as $endpoint) {
            $document->append(...$this->createEndpoint($endpoint));
        }

        $document->append(...$this->createSchemasList($api->getSchemas()));

        $document->append(...$this->createWarningBlock());

        return json_encode($document);
    }

    private function createTableOfContent(): Node
    {
        return new Extension(
            'default',
            'com.atlassian.confluence.macro.core',
            'toc',
            [
                'macroParams' => [
                    'minLevel' => [
                        'value' => '1',
                    ],
                    'maxLevel' => [
                        'value' => '3',
                    ],
                    'outline' => [
                        'value' => 'false',
                    ],
                    'type' => [
                        'value' => 'list',
                    ],
                    'printable' => [
                        'value' => 'false',
                    ],
                ],
            ],
            '123cad9c-95d8-49df-8b99-00886ba0af5d', // Avoid using a dynamic UUID to be able to generate a checksum
        );
    }

    private function createEndpoint(array $endpoint): array
    {
        $nodes = [];

        // Summary, method type, URI and description
        $nodes[] = (new Heading(1))->append(
            new Text($endpoint['summary'])
        );

        $nodes[] = (new Heading(3))->append(
            new Text(sprintf('%s %s%s', $endpoint['method'], $endpoint['pathPrefix'], $endpoint['path']))
        );

        if (!empty($endpoint['description'])) {
            $nodes = array_merge($nodes, $this->createFromMarkdown($endpoint['description']));
        }

        // Example header
        $nodes[] = (new Heading(3))->append(
            new Text('Example')
        );

        // Curl example
        $nodes[] = $this->createCurlExample($endpoint);

        if ($endpoint['requestPathParameters']) {

            // Path parameters header
            $nodes[] = (new Heading(3))->append(
                new Text('Path parameters')
            );

            $nodes[] = $this->createParametersTable($endpoint['requestPathParameters']);
        }

        if ($endpoint['requestQueryParameters']) {

            // Path parameters header

            $nodes[] = (new Heading(3))->append(
                new Text('Query parameters')
            );

            $nodes[] = $this->createParametersTable($endpoint['requestQueryParameters']);
        }

        if ($endpoint['requestBodies']) {

            // Request body header
            $nodes[] = (new Heading(3))->append(
                new Text('Request body')
            );

            // Request body details
            foreach ($endpoint['requestBodies'] as $requestBody) {
                $nodes = array_merge($nodes, $this->createRequestBody($requestBody));
            }
        }

        if ($endpoint['responses']) {

            // Response header
            $nodes[] = (new Heading(3))->append(
                new Text('Response')
            );

            // Responses descriptions
            foreach ($endpoint['responses'] as $responseCode => $response) {
                $nodes = array_merge($nodes, $this->createResponse((string) $responseCode, $response));
            }
        }

        return $nodes;
    }

    private function createRequestBody(array $requestBody): array
    {
        $nodes = [];

        // Request content type
        $nodes[] = (new Paragraph())->append(
            new Text('Content type: '),
            new Text($requestBody['contentType'], new Code()),
        );

        // Schema name (if the schema is named)
        if ($requestBody['schema'] && $requestBody['schemaName']) {
            $schemaName = new Paragraph();

            if ($requestBody['isArray']) {
                $schemaName->append(new Text('Array of '));
            }

            $schemaName->append($this->createSchemaLink($requestBody['schemaName']));
            $nodes[] = $schemaName;
        }

        // Schema table
        if ($requestBody['schema'] && 'object' === ($requestBody['schema']['type'] ?? '')) {
            $nodes[] = $this->createSchemaTable($requestBody['schema'], true);
        }

        // Schema table
        if ($requestBody['example']) {
            $nodes[] = (new Heading(4))->append(
                new Text('Example:')
            );
            $nodes[] = $this->createSchemaExample($requestBody['example'], $requestBody['contentType']);
        }

        return $nodes;
    }

    private function createResponse(string $responseCode, array $response): array
    {
        $nodes = [];

        // Response status code and description
        $nodes[] = (new Paragraph())->append(
            new Text($responseCode, new Strong()),
            new Text(sprintf(' %s', $response['description'])),
        );

        // Response content
        foreach ($response['content'] as $contentType => $content) {

            // Response content type
            $nodes[] = (new Paragraph())->append(
                new Text('Content type: '),
                new Text($contentType, new Code()),
            );

            // Schema name (if the schema is named)
            if ($content['schema'] && $content['schemaName']) {
                $schemaName = new Paragraph();

                if ($content['isArray']) {
                    $schemaName->append(new Text('Array of '));
                }

                $schemaName->append($this->createSchemaLink($content['schemaName']));
                $nodes[] = $schemaName;
            }

            // Schema table
            if ($content['schema'] && 'object' === $content['schema']['type']) {
                $nodes[] = $this->createSchemaTable($content['schema']);
            }

            // Schema table
            if ($content['example']) {
                $nodes[] = (new Heading(4))->append(
                    new Text('Example:')
                );
                $nodes[] = $this->createSchemaExample($content['example'], $contentType);
            }

            if (!empty($content['examples'])) {
                $nodes[] = (new Heading(4))->append(
                    new Text('Examples:')
                );

                foreach ($content['examples'] as $exampleName => $exampleData) {
                    $nodes[] = (new Paragraph())->append(
                        new Text(sprintf('%s:', $exampleName), new Strong()),
                        new Text(isset($exampleData['summary']) ? ' ' . $exampleData['summary'] : '')
                    );

                    $nodes[] = $this->createSchemaExample($exampleData['value'], $contentType);
                }
            }
        }

        return $nodes;
    }

    private function createSchemaTable(array $schema, $showRequired = false): Node
    {
        $header = new TableRow();
        // 'Name' header cell
        $header->append(
            (new TableHeader())->append(
                (new Paragraph())->append(new Text('Name', new Strong()))
            )
        );
        // 'Type' header cell
        $header->append(
            (new TableHeader())->append(
                (new Paragraph())->append(new Text('Type', new Strong()))
            )
        );
        // 'Required' header cell
        if ($showRequired) {
            $header->append(
                (new TableHeader())->append(
                    (new Paragraph())->append(new Text('Required', new Strong()))
                )
            );
        }
        // 'Description' header cell
        $header->append(
            (new TableHeader())->append(
                (new Paragraph())->append(new Text('Description', new Strong()))
            )
        );

        $table = new Table();
        $table->append($header);

        foreach ($schema['properties'] as $propertyName => $property) {
            $row = new TableRow();

            // 'Name' cell
            $row->append(
                (new TableCell())->append(
                    (new Paragraph())->append(new Text($propertyName))
                )
            );

            // 'Type' cell
            $row->append(
                (new TableCell())->append(
                    $this->createPropertyType($property)
                )
            );

            // 'Required' cell
            if ($showRequired) {
                $required = in_array($propertyName, $schema['required'] ?? [], true);
                $row->append(
                    (new TableCell())->append(
                        (new Paragraph())->append(
                            new Status($required ? 'required' : 'optional', $required ? 'red' : 'blue', null, 'bold')
                        )
                    )
                );
            }

            // 'Description' cell
            $row->append(
                (new TableCell())->append(
                    $this->createPropertyDescription($property)
                )
            );

            $table->append($row);
        }

        return $table;
    }

    private function createParametersTable(array $parameters): Node
    {
        $header = new TableRow();
        // 'Name' header cell
        $header->append(
            (new TableHeader())->append(
                (new Paragraph())->append(new Text('Name', new Strong()))
            )
        );
        // 'Type' header cell
        $header->append(
            (new TableHeader())->append(
                (new Paragraph())->append(new Text('Type', new Strong()))
            )
        );
        // 'Explode' header cell
        $header->append(
            (new TableHeader())->append(
                (new Paragraph())->append(new Text('Explode', new Strong()))
            )
        );
        // 'Required' header cell
        $header->append(
            (new TableHeader())->append(
                (new Paragraph())->append(new Text('Required', new Strong()))
            )
        );
        // 'Description' header cell
        $header->append(
            (new TableHeader())->append(
                (new Paragraph())->append(new Text('Description', new Strong()))
            )
        );

        $table = new Table();
        $table->append($header);

        foreach ($parameters as $parameter) {
            $row = new TableRow();

            // 'Name' cell
            $row->append(
                (new TableCell())->append(
                    (new Paragraph())->append(new Text($parameter['name'] ?? ''))
                )
            );

            // 'Type' cell
            $row->append(
                (new TableCell())->append(
                    (new Paragraph())->append(new Text($parameter['schema']['type'] ?? ''))
                )
            );

            // 'Explode' cell
            $text = ($parameter['explode'] ?? false) ? 'true' : 'false';
            $color = ($parameter['explode'] ?? false) ? 'green' : 'neutral';
            $row->append(
                (new TableCell())->append(
                    (new Paragraph())->append(new Status($text, $color, null, 'bold'))
                )
            );

            // 'Required' cell
            $text = ($parameter['required'] ?? false) ? 'required' : 'optional';
            $color = ($parameter['required'] ?? false) ? 'red' : 'blue';
            $row->append(
                (new TableCell())->append(
                    (new Paragraph())->append(new Status($text, $color, null, 'bold'))
                )
            );

            // 'Description' header cell
            $row->append(
                (new TableCell())->append(
                    (new Paragraph())->append(new Text($parameter['description'] ?? ''))
                )
            );

            $table->append($row);
        }

        return $table;
    }

    private function createCurlExample(array $endpoint): Node
    {
        $cmdLines = [];

        $cmdLines[] = sprintf('curl -X %s %s%s', $endpoint['method'], $endpoint['pathPrefix'], $endpoint['path']);
        foreach ($endpoint['requestHeaders'] as $headerName => $headerValue) {
            $cmdLines[] = sprintf("  --header '%s: %s'", $headerName, $headerValue);
        }
        foreach ($endpoint['requestCookies'] as $headerName => $headerValue) {
            $cmdLines[] = sprintf("  --cookie '%s=%s'", urlencode($headerName), urlencode($headerValue));
        }
        foreach ($endpoint['requestBodies'] as $requestBody) {
            if (!is_null($requestBody['example'])) {
                $cmdLines[] = sprintf("  --data '%s'", json_encode($requestBody['example']));
            }
            break;
        }

        return (new CodeBlock('shell'))->append(new Text(implode(" \\\n", $cmdLines)));
    }

    private function createSchemaExample($example, string $contentType): Node
    {
        if ('application/json' === $contentType) {
            $node = (new CodeBlock('json'))->append(
                new Text(json_encode($example, JSON_PRETTY_PRINT))
            );
        } else {
            $node = (new CodeBlock())->append(
                new Text((string) $example)
            );
        }

        return $node;
    }

    private function createSchemasList(array $schemas): array
    {
        $nodes = [];

        // Heading
        $nodes[] = (new Heading(1))->append(
            new Text('Schemas')
        );

        foreach ($schemas as $schemaName => $schema) {
            // Schema name heading
            $nodes[] = (new Heading(3))->append(
                new Text($schemaName)
            );

            // Schema description
            if (!empty($schema['description'])) {
                $nodes[] = (new Paragraph())->append(
                    new Text($schema['description'])
                );
            }

            // Schema table
            $nodes[] = $this->createSchemaTable($schema);
        }

        return $nodes;
    }

    private function createSchemaLink(string $schemaName): Node
    {
        return new Text($schemaName, new Link('#' . $schemaName), new Strong());
    }

    private function createWarningBlock(): array
    {
        return [
            (new Paragraph()),
            (new Paragraph()),
            (new Panel(Panel::INFO))->append(
                (new Heading(6))->append(
                    new Text('This page was automatically generated.')
                ),
                (new Heading(6))->append(
                    new Text(
                        'Please do not change this page manually. '
                        . 'All changes made will be overwritten at the next automatic update.'
                    )
                )
            )
        ];
    }
}
