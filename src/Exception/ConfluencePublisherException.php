<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Exception;

class ConfluencePublisherException extends CommandException
{
    public static function invalidPageIdFormat(string $apiName): static
    {
        return new static(
            [
                sprintf(
                    'The provided page-id value for API "%s" does not match the format. Numerical value is expected.',
                    $apiName,
                ),
            ],
        );
    }

    public static function pageNotFound(string $apiName, string $pageId): static
    {
        return new static(
            [
                sprintf('The provided page-id "%s" for API "%s" was not found on confluence.', $pageId, $apiName),
                'Make sure the page-id is correct in the configuration file'
                . 'or remove the page-id value for a specific API section so that the page is created automatically.',
            ],
        );
    }
}
