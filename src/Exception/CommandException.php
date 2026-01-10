<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Exception;

use Exception;

class CommandException extends Exception
{
    protected array $messages = [];

    protected function __construct(array $messages)
    {
        $this->messages = $messages;
        parent::__construct();
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public static function missingConfigFile(string $configName): CommandException
    {
        return new static(
            [
                sprintf('No "%s" file found.', $configName),
                'Make sure you are in the correct working directory or '
                . 'run the command "confluence-api-doc init" to configure a new project in that directory.',
            ]
        );
    }

    public static function notReadableConfigFile(string $configPath): CommandException
    {
        return new static(
            [
                sprintf('The configuration file "%s" cannot be read.', $configPath),
            ]
        );
    }

    public static function notWritableConfigFile(string $configPath): CommandException
    {
        return new static(
            [
                sprintf('The configuration file "%s" cannot be written.', $configPath),
            ]
        );
    }

    public static function corruptedConfigFile(string $configPath): CommandException
    {
        return new static(
            [
                sprintf('The configuration file "%s" is corrupted.', $configPath),
                'Remove the corrupted configuration file and run the command "confluence-api-doc init" '
                . 'to configure a new project in that directory.',
            ]
        );
    }

    public static function missingConfigKey(string $key, string $configPath): CommandException
    {
        return new static(
            [
                sprintf('Config key "%s" is missing or empty.', $key),
                sprintf('Configuration file "%s"', $configPath),
            ]
        );
    }

    public static function wrongConfigKeyType(
        string $key,
        string $expected,
        string $actual,
        string $configPath
    ): CommandException {
        return new static(
            [
                sprintf('Config key "%s" value is expected to be "%s", "%s" is provided.', $key, $expected, $actual),
                sprintf('Configuration file "%s"', $configPath),
            ]
        );
    }

    public static function wrongGeneratorClass(string $actual, string $expected): CommandException {
        return new static(
            [
                sprintf('The class "%s" can not be used as "generator". ', $actual),
                sprintf('It is expected that the provided class implement "%s" interface.', $expected),
            ]
        );
    }
}
