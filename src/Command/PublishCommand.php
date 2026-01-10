<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Command;

use ConfluenceApiDoc\ConfluenceClient;
use ConfluenceApiDoc\ConfluencePublisher;
use ConfluenceApiDoc\Exception\OpenApiProviderException;
use ConfluenceApiDoc\Generator\DefaultGenerator;
use ConfluenceApiDoc\Generator\DocumentGeneratorInterface;
use ConfluenceApiDoc\Exception\CommandException;
use ConfluenceApiDoc\OpenApiProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PublishCommand extends AbstractCommand
{
    protected static $defaultName = 'publish';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    /**
     * @inheritDoc
     */
    protected function executeSafely(InputInterface $input, OutputInterface $output, LoggerInterface $logger): int
    {
        $config = $this->loadConfig();
        $publisher = new ConfluencePublisher($logger, new ConfluenceClient($config['confluence']));
        $apiDocConfig = $this->loadApiDocConfig();

        $io = new SymfonyStyle($input, $output);
        $io->writeln('');

        foreach ($apiDocConfig['apis'] as $apiName => $apiConfig) {
            $docGenerator = $this->createDocumentGenerator($apiConfig);
            $openApiProvider = $this->createOpenApiProvider($apiConfig['openapi']);

            if (version_compare($openApiProvider->getVersion(), '3.0.0', '<')) {
                $io->warning([
                    sprintf('The "%s" API provided a file with an OpenAPI version lower than 3.0.0.', $apiName),
                    'For the package to work correctly, version OpenAPI >=3.0.0 is expected. '
                    . 'With other versions the correct operation is not guaranteed.',
                ]);
            }

            $result = $publisher->process($docGenerator, $openApiProvider, $apiName, $apiConfig);

            // Update api config with information about the latest update
            $apiDocConfig['apis'][$apiName] = array_merge($apiDocConfig['apis'][$apiName], $result['info']);

            // Output the API page status
            if (ConfluencePublisher::STATUS_PUBLISHED === $result['status']) {
                $io->writeln(
                    sprintf(
                        ' * API <info>"%s"</info> <fg=black;bg=green> PUBLISHED </> — confluence page %s',
                        $apiName,
                        $result['info']['page-url']
                    ));
            } else {
                $io->writeln(
                    sprintf(
                        ' * API <info>"%s"</info> <fg=black;bg=gray> SKIPPED </> — no changes to publish',
                        $apiName
                    )
                );
            }
        }

        // Save updated apis information into the config file
        $this->saveApiDocConfig($apiDocConfig);

        $io->success('Complete');

        return Command::SUCCESS;
    }

    /**
     * Returns an instance of document generator for the provided api config
     *
     * @param array $apiConfig
     *
     * @return DocumentGeneratorInterface
     *
     * @throws CommandException
     */
    private function createDocumentGenerator(array $apiConfig): DocumentGeneratorInterface
    {
        $generatorClass = DefaultGenerator::class;
        if (!empty($apiConfig['generator'])) {
            $generatorClass = (string) $apiConfig['generator'];
        }

        if (!class_exists($generatorClass) || !is_subclass_of($generatorClass, DocumentGeneratorInterface::class)) {
            throw CommandException::wrongGeneratorClass($generatorClass, DocumentGeneratorInterface::class);
        }

        return new $generatorClass;
    }

    /**
     * Returns an instance of OpenApiProvider for the provided OpenAPI file
     *
     * @param string $openApiFile
     *
     * @return OpenApiProvider
     *
     * @throws OpenApiProviderException
     */
    private function createOpenApiProvider(string $openApiFile): OpenApiProvider
    {
        return new OpenApiProvider($this->getWorkingDirPath() . DIRECTORY_SEPARATOR . $openApiFile);
    }
}
