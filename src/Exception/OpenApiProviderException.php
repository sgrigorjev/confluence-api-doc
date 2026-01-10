<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Exception;

class OpenApiProviderException extends CommandException
{
    public static function missingOpenApiFile(string $path): static
    {
        return new static(
            [
                sprintf('OpenAPI file "%s" is not found.', $path),
            ],
        );
    }

    public static function notReadableOpenApiFile(string $path): static
    {
        return new static(
            [
                sprintf('OpenAPI file "%s" cannot be read.', $path),
            ],
        );
    }

    public static function unsupportedOpenApiFileType(string $path, array $supportedTypes): static
    {
        return new static(
            [
                sprintf('OpenAPI file "%s" in an unsupported format.', $path),
                'Supported file extensions are: ' . implode(', ', $supportedTypes),
            ],
        );
    }

    public static function corruptedOpenApiFile(string $path): static
    {
        return new static(
            [
                sprintf('OpenAPI file "%s" contains unexpected content.', $path),
                'The contents of the file are expected to be represented as an object/array',
            ],
        );
    }
}
