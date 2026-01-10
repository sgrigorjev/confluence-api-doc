<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Exception;

class ConfluenceClientException extends CommandException
{
    public static function responseError(string $error): static
    {
        $messages = ['Invalid response'];
        $messages = array_merge($messages, preg_split("/\n/", $error, 0, PREG_SPLIT_NO_EMPTY));

        return new static($messages);
    }

    public static function responseDecodingError(
        string $method,
        string $uri,
        string $data,
        string $error,
    ): static {
        $messages = [];
        $messages[] = 'Invalid JSON response is received from ' . $method . ' ' . $uri;
        $messages[] = 'Response body:';
        $messages = array_merge($messages, preg_split("/\n/", $data, 0, PREG_SPLIT_NO_EMPTY));
        $messages[] = $error;

        return new static($messages);
    }

    public static function scopeKeyNotFound(string $key): static
    {
        return new static(
            [
                sprintf('Scope with key "%s" was not found on confluence.', $key),
            ],
        );
    }
}
