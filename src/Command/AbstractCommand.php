<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Command;

use ConfluenceApiDoc\Exception\CommandException;
use ConfluenceApiDoc\Logger\ConsoleLogger;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractCommand extends Command
{
    protected const CONFIG_FILENAME = 'confluence.yaml';
    protected const CONFIG_PREPEND_YAML_PATH = __DIR__ . '/../confluence.prepend.yaml';
    protected const API_DOC_CONFIG_FILENAME = 'confluence-api-doc.yaml';
    protected const API_DOC_CONFIG_PREPEND_YAML_PATH = __DIR__ . '/../confluence-api-doc.prepend.yaml';

    private LoggerInterface $logger;

    protected function getWorkingDirPath(): string
    {
        return getcwd();
    }

    protected function getConfigPath(string $fileName): string
    {
        return $this->getWorkingDirPath() . DIRECTORY_SEPARATOR . $fileName;
    }

    protected function isConfigFileExists(string $fileName): bool
    {
        return file_exists($this->getConfigPath($fileName));
    }

    private function saveConfigFile(string $path, string $content): void
    {
        if (!file_put_contents($path, $content)) {
            CommandException::notWritableConfigFile($path);
        }
    }

    protected function saveConfig(array $config): void
    {
        $path = $this->getConfigPath(self::CONFIG_FILENAME);

        $this->logger->info(sprintf('Saving config file %s', $path));
        $this->logger->debug(sprintf('Saving config file %s data', $path), $config);

        $yaml = file_get_contents(self::CONFIG_PREPEND_YAML_PATH);
        $yaml .= Yaml::dump($config, 4);
        $this->saveConfigFile($path, $yaml);
    }

    protected function saveApiDocConfig(array $config): void
    {
        $path = $this->getConfigPath(self::API_DOC_CONFIG_FILENAME);

        $this->logger->info(sprintf('Saving config file %s', $path));
        $this->logger->debug(sprintf('Saving config file %s data', $path), $config);

        $yaml = file_get_contents(self::API_DOC_CONFIG_PREPEND_YAML_PATH);
        $yaml .= Yaml::dump($config, 4);
        $this->saveConfigFile($path, $yaml);
    }

    /**
     * @param string $path
     *
     * @return array
     *
     * @throws CommandException
     */
    private function loadConfigByPath(string $path): array
    {
        $this->logger->info(sprintf('Loading config file %s', $path));

        if (!is_readable($path)) {
            throw CommandException::notReadableConfigFile($path);
        }

        $config = Yaml::parseFile($path);
        if (!$config || !is_array($config)) {
            throw CommandException::corruptedConfigFile($path);
        }

        $this->logger->debug(sprintf('Config file %s loaded', $path), $config);

        return $config;
    }

    /**
     * @return array
     *
     * @throws CommandException
     */
    protected function loadConfig(): array
    {
        if (!$this->isConfigFileExists(self::CONFIG_FILENAME)) {
            throw CommandException::missingConfigFile(self::CONFIG_FILENAME);
        }

        $path = $this->getConfigPath(self::CONFIG_FILENAME);
        $config = $this->loadConfigByPath($path);

        $this->validateConfigKey($path, $config, ['confluence', 'host']);
        $this->validateConfigKey($path, $config, ['confluence', 'user']);
        $this->validateConfigKey($path, $config, ['confluence', 'token']);

        return $config;
    }

    /**
     * @return array
     *
     * @throws CommandException
     */
    protected function loadApiDocConfig(): array
    {
        if (!$this->isConfigFileExists(self::API_DOC_CONFIG_FILENAME)) {
            throw CommandException::missingConfigFile(self::API_DOC_CONFIG_FILENAME);
        }

        $path = $this->getConfigPath(self::API_DOC_CONFIG_FILENAME);
        $config = $this->loadConfigByPath($path);

        $this->validateConfigKey($path, $config, ['apis'], ['array']);
        foreach (array_keys($config['apis']) as $apiName) {
            $this->validateConfigKey($path, $config, ['apis', $apiName, 'openapi']);
            $this->validateConfigKey($path, $config, ['apis', $apiName, 'scope-key']);
            $this->validateConfigKey($path, $config, ['apis', $apiName, 'page-title']);
        }

        return $config;
    }

    /**
     * @param string $path
     * @param array $config
     * @param array $keyPath
     * @param array $expectedType
     *
     * @throws CommandException
     */
    private function validateConfigKey(
        string $path,
        array $config,
        array $keyPath,
        array $expectedType = ['string', 'integer'],
    ): void {

        $depth = count($keyPath);
        $cursor = $config;
        $cursorKeyPath = [];

        foreach ($keyPath as $i => $key) {
            $cursorKeyPath[] = $key;
            $currentKeyPath = implode('.', $cursorKeyPath);
            $type = gettype($cursor[$key]);

            if (empty($cursor[$key])) {
                throw CommandException::missingConfigKey($currentKeyPath, $path);
            }

            if ($i < $depth - 1) {
                // This is not the last key in the chain, so the value must be an array.
                if (!is_array($cursor[$key])) {
                    throw CommandException::wrongConfigKeyType($currentKeyPath, 'array', $type, $path);
                }
            } elseif (!in_array($type, $expectedType, true)) {
                // This is the last key in the chain, so the value must match the expected type.
                throw CommandException::wrongConfigKeyType($currentKeyPath, implode(', ', $expectedType), $type, $path);
            }

            $cursor = $cursor[$key];
        }
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $this->logger = new ConsoleLogger($output);
            $code = $this->executeSafely($input, $output, $this->logger);
        } catch (CommandException $e) {
            $io->error($e->getMessages());
            $code = Command::FAILURE;
        } catch (Exception $e) {
            $io->error(explode("\n", (string) $e));
            $code = Command::FAILURE;
        }

        return $code;
    }

    /**
     * Executes the current command with exception handling.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param LoggerInterface $logger
     * @return int
     *
     * @throws CommandException
     * @throws Exception
     */
    abstract protected function executeSafely(
        InputInterface $input,
        OutputInterface $output,
        LoggerInterface $logger,
    ): int;
}
