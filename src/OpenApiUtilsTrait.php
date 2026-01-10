<?php

declare(strict_types=1);

namespace ConfluenceApiDoc;

trait OpenApiUtilsTrait
{
    public function getSchemaNameByRef(string $ref): string
    {
        if (str_starts_with($ref, '#/components/schemas/')) {
            return mb_substr($ref, 21);
        }

        return $ref;
    }
}
