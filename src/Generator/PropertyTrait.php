<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Generator;

use DH\Adf\Node\Block\Paragraph;
use DH\Adf\Node\Inline\Status;
use DH\Adf\Node\Inline\Text;
use DH\Adf\Node\Node;

trait PropertyTrait
{
    /**
     * Creates a node with information about a type or types based on the provided property schema
     *
     * @param array $property
     *
     * @return Node
     */
    public function createPropertyType(array $property): Node
    {
        $nodes = [];

        if (isset($property['oneOf']) && is_array($property['oneOf'])) {
            $amount = count($property['oneOf']);
            foreach ($property['oneOf'] as $i => $subProperty) {
                $nodes = array_merge($nodes, $this->createPropertySingleType($subProperty));
                if ($i < $amount - 1) {
                    $nodes[] = new Text("\n");
                }
            }
        } else {
            $nodes = array_merge($nodes, $this->createPropertySingleType($property));
        }

        return (new Paragraph())->append(...$nodes);
    }

    /**
     * Creates a set of nodes with information about single type based on the provided property schema
     *
     * @param array $property
     *
     * @return Node[]
     */
    private function createPropertySingleType(array $property): array
    {
        $nodes = [];
        $property['type'] = $property['type'] ?? '';
        $isArray = false;
        $isNamedSchema = false;

        if ('array' === $property['type'] && isset($property['items'])) {
            // The property value is an array of types
            $isArray = true;
            if (isset($property['items']['$ref'])) {
                // The array element is a named schema
                $isNamedSchema = true;
                $type = $this->getSchemaNameByRef($property['items']['$ref']);
            } else {
                // The array element is of simple type
                $type = (string) ($property['items']['type'] ?? '');
            }
        } elseif (isset($property['$ref'])) {
            // The property value is a named schema
            $isNamedSchema = true;
            $type = $this->getSchemaNameByRef($property['$ref']);
        } else {
            // The property value is of simple type
            $type = (string) $property['type'];
        }

        if ($isArray) {
            // Open a notation that the value is an array of a specific type
            $nodes[] = new Text('array<');
        }

        if ($isNamedSchema) {
            // If the type is a named schema, we append it as a schema reference
            $nodes[] = $this->createSchemaLink($type);
        } else {
            // Otherwise we just append a type
            $nodes[] = new Text($type);
        }

        if ($isArray) {
            // Close notation that the value is an array of a specific type
            $nodes[] = new Text('>');
        }

        return $nodes;
    }

    /**
     * Creates a node with information about the property description and the property's default value
     *
     * @param array $property
     *
     * @return Node
     */
    public function createPropertyDescription(array $property): Node
    {
        $nodes = [];

        if (!empty($property['description'])) {
            $nodes[] = new Text($property['description']);
        }

        if (isset($property['default'])) {
            if ($nodes) {
                $nodes[] = new Text("\n");
            }
            $nodes[] = new Status('Default: ' . var_export($property['default'], true), Status::COLOR_PURPLE);
        }

        return (new Paragraph())->append(...$nodes);
    }
}
