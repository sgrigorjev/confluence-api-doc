<?php

declare(strict_types=1);

namespace ConfluenceApiDoc;

use ConfluenceApiDoc\Exception\ConfluenceClientException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class ConfluenceClient
{
    private Client $http;

    private string $host;

    public function __construct(array $config)
    {
        $this->http = new Client([
            'base_uri' => $config['host'],
            'auth' => [$config['user'], $config['token']],
            'headers' => [
                'User-Agent' => 'ConfluenceApiDoc publishing tool',
            ],
        ]);
        $this->host = $config['host'];
    }

    /**
     * Returns a specific space.
     *
     * @param string $key Space key
     *
     * @return array
     *
     * @throws ConfluenceClientException
     */
    public function getScopeByKey(string $key): array
    {
        $uri = sprintf('/wiki/api/v2/spaces?%s', http_build_query(['keys' => $key, 'limit' => 1]));

        try {
            $response = $this->http->get($uri);
        } catch (GuzzleException $e) {
            throw ConfluenceClientException::responseError($e->getMessage());
        }

        $data = $this->responseDecode('GET', $uri, $response);

        if (empty($data['results'][0])) {
            throw ConfluenceClientException::scopeKeyNotFound($key);
        }

        return $data['results'][0];
    }

    /**
     * Returns a specific page.
     *
     * @param string $pageId Parent ID
     *
     * @return array|null
     *
     * @throws ConfluenceClientException
     */
    public function getPage(string $pageId): ?array
    {
        $uri = '/wiki/api/v2/pages/' . $pageId . '?body-format=atlas_doc_format';

        try {
            $response = $this->http->get($uri);
        } catch (GuzzleException $e) {
            if ($e instanceof BadResponseException && 404 === $e->getResponse()->getStatusCode()) {
                return null;
            }
            throw ConfluenceClientException::responseError($e->getMessage());
        }

        return $this->responseDecode('GET', $uri, $response);
    }

    /**
     * Creates a page in the specific space and parent.
     *
     * @param string $spaceId Scope ID
     * @param string $parentId Parent page ID
     * @param string $title Page title
     * @param string $content Page content value in Atlassian Document Format (ADF)
     *
     * @return array
     *
     * @throws ConfluenceClientException
     */
    public function createPage(string $spaceId, string $parentId, string $title, string $content): array
    {
        $uri = '/wiki/api/v2/pages';

        try {
            $response = $this->http->post($uri, [
                'json' => [
                    'spaceId' => $spaceId,
                    'parentId' => $parentId,
                    'status' => 'current',
                    'type' => 'page',
                    'title' => $title,
                    'body' => [
                        'atlas_doc_format' => [
                            'value' => $content,
                            'representation' => 'atlas_doc_format',
                        ],
                    ],
                    'version' => [
                        'number' => 1,
                        'message' => 'Test curl page creation',
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            throw ConfluenceClientException::responseError($e->getMessage());
        }

        return $this->responseDecode('POST', $uri, $response);
    }

    /**
     * Update a page by ID.
     *
     * @param string $pageId Page ID
     * @param string $title Page title
     * @param string $content Page content value in Atlassian Document Format (ADF)
     * @param int $version Page version number. There should be an increment of the current version
     * @param string $reason The reason for editing the page (will be displayed in history)
     *
     * @return array
     *
     * @throws ConfluenceClientException
     */
    public function updatePage(string $pageId, string $title, string $content, int $version, string $reason = ''): array
    {
        $uri = '/wiki/api/v2/pages/' . $pageId;

        try {
            $response = $this->http->put($uri, [
                'json' => [
                    'id' => $pageId,
                    'status' => 'current',
                    'version' => [
                        'number' => $version,
                        'message' => $reason,
                    ],
                    'type' => 'page',
                    'title' => $title,
                    'body' => [
                        'atlas_doc_format' => [
                            'value' => $content,
                            'representation' => 'atlas_doc_format',
                        ],
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            throw ConfluenceClientException::responseError($e->getMessage());
        }

        return $this->responseDecode('PUT', $uri, $response);
    }

    /**
     * Decodes a confluence response.
     *
     * @param string $method
     * @param string $uri
     * @param ResponseInterface $response
     *
     * @return array
     *
     * @throws ConfluenceClientException
     */
    private function responseDecode(string $method, string $uri, ResponseInterface $response): array
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true);
        $error = '';

        if (json_last_error()) {
            $error = json_last_error_msg();
        } elseif (!is_array($data)) {
            $error = 'Is not array';
        }

        if ($error) {
            throw ConfluenceClientException::responseDecodingError($method, $uri, $json, $error);
        }

        return $data;
    }

    /**
     * Returns the confluence host used.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }
}
