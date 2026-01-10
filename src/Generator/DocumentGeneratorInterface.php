<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Generator;

use ConfluenceApiDoc\OpenApiProvider;

interface DocumentGeneratorInterface
{
    public function generate(OpenApiProvider $api): string;
}
