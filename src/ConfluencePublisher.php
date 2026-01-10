<?php

declare(strict_types=1);

namespace ConfluenceApiDoc;

use ConfluenceApiDoc\Generator\DocumentGeneratorInterface;
use ConfluenceApiDoc\Exception\ConfluenceClientException;
use ConfluenceApiDoc\Exception\ConfluencePublisherException;
use ConfluenceApiDoc\Exception\OpenApiProviderException;
use Psr\Log\LoggerInterface;

class ConfluencePublisher
{
    public const STATUS_PUBLISHED = 1;
    public const STATUS_SKIPPED = 2;

    private LoggerInterface $logger;

    private ConfluenceClient $client;

    private array $scopesCache = [];

    /**
     * @param LoggerInterface $logger
     * @param ConfluenceClient $client
     */
    public function __construct(LoggerInterface $logger, ConfluenceClient $client)
    {
        $this->logger = $logger;
        $this->client = $client;
    }

    /**
     * @param DocumentGeneratorInterface $generator
     * @param OpenApiProvider $openApiProvider
     * @param string $apiName API name
     * @param array $apiConfig API configuration array
     *
     * @return array Updated page configuration array
     *
     * @throws ConfluenceClientException
     * @throws ConfluencePublisherException
     */
    public function process(
        DocumentGeneratorInterface $generator,
        OpenApiProvider $openApiProvider,
        string $apiName,
        array $apiConfig
    ): array {

        $this->logger->info(sprintf('Start processing API "%s"', $apiName));
        $this->logger->debug(sprintf('Processing API "%s"', $apiName), [
            'generator' => get_class($generator),
        ]);

        $content = $generator->generate($openApiProvider);
        $checksum = $this->createContentChecksum($content);
        $info = [];

        if (isset($apiConfig['checksum']) && $checksum === $apiConfig['checksum']) {
            $this->logger->info(
                sprintf('Content checksum of the API "%s" was not changed. Publishing skipped.', $apiName)
            );
            $status = self::STATUS_SKIPPED;
        } else {
            $page = $this->publish($apiName, $apiConfig, $content);
            $status = self::STATUS_PUBLISHED;
            $info = [
                'page-id' => $page['page-id'],
                'page-url' => $page['page-url'],
                'published-at' => date('r'),
                'checksum' => $checksum,
            ];
        }

        return [
            'status' => $status,
            'info' => $info,
        ];
    }

    /**
     * Publishes content for the provided API
     *
     * @param string $apiName
     * @param array $apiConfig
     * @param string $content
     *
     * @return array
     *
     * @throws ConfluenceClientException
     * @throws ConfluencePublisherException
     */
    private function publish(string $apiName, array $apiConfig, string $content): array
    {
        if (isset($apiConfig['page-id'])) {
            // Update existing page
            $page = $this->updatePage($apiName, (string) $apiConfig['page-id'], $content);
        } else {
            // Create new page
            $scope = $this->getScope((string) $apiConfig['scope-key']);
            $page = $this->createPage(
                $apiName,
                (string) $scope['id'],
                (string) $apiConfig['parent-id'],
                (string) $apiConfig['page-title'],
                $content
            );
        }

        return $page;
    }

    /**
     * Updates an existing confluence page
     *
     * @param string $apiName API name (internal value)
     * @param string $pageId Page ID
     * @param string $content Page content value in Atlassian Document Format (ADF)
     *
     * @return array Page information
     *
     * @throws ConfluenceClientException
     * @throws ConfluencePublisherException
     */
    private function updatePage(string $apiName, string $pageId, string $content): array
    {
        if (!is_numeric($pageId)) {
            throw ConfluencePublisherException::invalidPageIdFormat($apiName);
        }

        $this->logger->info(sprintf('Loading confluence page for API "%s"', $apiName), [
            'page-id' => $pageId,
        ]);

        $page = $this->client->getPage($pageId);

        $this->logger->info(sprintf('Confluence page for API "%s" was loaded', $apiName));
        $this->logger->debug(sprintf('Loaded confluence page for API "%s"', $apiName), $page ?? []);

        if (is_null($page)) {
            throw ConfluencePublisherException::pageNotFound($apiName, $pageId);
        }

        $version = $page['version']['number'] + 1;
        $reason = 'Automatic update based on OpenAPI schema via auto-publishing tool';

        $this->logger->info(sprintf('Updating confluence page for API "%s"', $apiName), [
            'page-id' => $pageId,
            'page-title' => $page['title'],
            'version' => $version,
        ]);
        $this->logger->debug(sprintf('Updating confluence page for API "%s"', $apiName), [
            'page-id' => $pageId,
            'page-title' => $page['title'],
            'content' => $content,
            'version' => $version,
            'reason' => $reason,
        ]);

        $page = $this->client->updatePage($pageId, $page['title'], $content, $version, $reason);

        $this->logger->info(sprintf('Confluence page for API "%s" was updated', $apiName));
        $this->logger->debug(sprintf('Updated confluence page for API "%s"', $apiName), $page);

        return [
            'page-id' => $page['id'],
            'page-url' => $this->createUrl($page['_links']['webui']),
        ];
    }

    /**
     * Creates a new confluence page
     *
     * @param string $apiName API name (internal value)
     * @param string $scopeId Confluence space ID
     * @param string $parentId Confluence page ID of which the created page should be a subpage
     * @param string $title Page title
     * @param string $content Page content value in Atlassian Document Format (ADF)
     *
     * @return array
     *
     * @throws ConfluenceClientException
     */
    private function createPage(
        string $apiName,
        string $scopeId,
        string $parentId,
        string $title,
        string $content
    ): array {

        $this->logger->info(sprintf('Creating confluence page for API "%s"', $apiName), [
            'scope-id' => $scopeId,
            'parent-id' => $parentId,
            'page-title' => $title,
        ]);

        $page = $this->client->createPage($scopeId, $parentId, $title, $content);

        $this->logger->info(
            sprintf('Confluence page for API "%s" was created with page-id "%s"', $apiName, $page['id'])
        );
        $this->logger->debug(sprintf('Created confluence page for API "%s"', $apiName), $page);

        return [
            'page-id' => $page['id'],
            'page-url' => $this->createUrl($page['_links']['webui']),
        ];
    }

    /**
     * Returns a space by key. The cached value is used if available.
     *
     * @param string $scopeKey
     *
     * @return array
     *
     * @throws ConfluenceClientException
     */
    private function getScope(string $scopeKey): array
    {
        if (!isset($this->scopesCache[$scopeKey])) {
            $this->scopesCache[$scopeKey] = $this->client->getScopeByKey($scopeKey);
        }

        return $this->scopesCache[$scopeKey];
    }

    /**
     * Returns a valid confluence page URL based on provided page path.
     *
     * @param string $path
     *
     * @return string
     */
    private function createUrl(string $path): string
    {
        return $this->client->getHost() . '/wiki' . $path;
    }

    /**
     * Returns a checksum of the provided content.
     *
     * @param string $content
     *
     * @return string
     */
    private function createContentChecksum(string $content): string
    {
        return md5($content);
    }
}
