<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends AbstractCommand
{
    private const OVERWRITE_QUESTION = 'Configuration file "%s" already exists in this directory. '
                                     . 'Do you want to overwrite it and lose all its contents?';

    protected static $defaultName = 'init';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    /**
     * @inheritDoc
     */
    protected function executeSafely(InputInterface $input, OutputInterface $output, LoggerInterface $logger): int
    {
        $initConfig = true;
        $initApiDocConfig = true;
        $io = new SymfonyStyle($input, $output);

        if ($this->isConfigFileExists(self::CONFIG_FILENAME)) {
            $initConfig = $io->confirm(sprintf(self::OVERWRITE_QUESTION, self::CONFIG_FILENAME), false);
            $logger->debug(
                sprintf('Confirmation for overwriting %s', self::CONFIG_FILENAME),
                ['overwrite' => $initConfig]
            );
        }

        if ($initConfig) {
            $this->initConfig($io);
        }

        if ($this->isConfigFileExists(self::API_DOC_CONFIG_FILENAME)) {
            $initApiDocConfig = $io->confirm(sprintf(self::OVERWRITE_QUESTION, self::API_DOC_CONFIG_FILENAME), false);
            $logger->debug(
                sprintf('Confirmation for overwriting %s', self::API_DOC_CONFIG_FILENAME),
                ['overwrite' => $initApiDocConfig]
            );
        }

        if ($initApiDocConfig) {
            $this->initApiDocConfig($io);
        }

        if ($initConfig || $initApiDocConfig) {
            $io->success('Initialization complete');
        }

        return Command::SUCCESS;
    }

    protected function initConfig(SymfonyStyle $io): void
    {
        $io->info('Initializing the Confluence access settings');

        $config = [
            'confluence' => [
                'host' => '',
                'user' => '',
                'token' => '',
            ],
        ];

        if ($io->confirm('Would you like to fill out confluence credentials now?')) {
            $config['confluence']['host'] = $io->ask('Confluence host');
            $config['confluence']['user'] = $io->ask('Confluence user');
            $config['confluence']['token'] =$io->ask('Confluence API token');
        }

        $this->saveConfig($config);

        $io->info(sprintf('File "%s" was created', $this->getConfigPath(self::CONFIG_FILENAME)));
    }

    protected function initApiDocConfig(SymfonyStyle $io): void
    {
        $apiDocConfig = [
            'apis' => [
                'example' => [
                    'openapi' => 'docs/openapi/example.yaml',
                    'scope-key' => 'PROJECT1',
                ],
            ],
        ];

        $this->saveApiDocConfig($apiDocConfig);

        $io->info(sprintf('File "%s" was created', $this->getConfigPath(self::API_DOC_CONFIG_FILENAME)));

        $io->text(
            sprintf(
                'Fill file "%s" with information about the APIs that should be published',
                self::API_DOC_CONFIG_FILENAME
            )
        );
    }
}
