# confluence-api-doc

Turns an OpenAPI 3.x document into a native Confluence page (ADF) and publishes it through the Confluence REST API v2. Each run compares the generated content with the last published checksum and skips pages that did not change.

## Install

```bash
git clone https://github.com/sgrigorjev/confluence-api-doc.git
cd confluence-api-doc
composer install
```

Run `bin/confluence-api-doc` from the directory of the project whose API you publish. The two config files below are read from the current directory.

## Init

```bash
/path/to/confluence-api-doc/bin/confluence-api-doc init
```

Writes two files into the current directory:

- `confluence.yaml` holds the Confluence host, user and API token. Add it to `.gitignore`.
- `confluence-api-doc.yaml` lists the APIs to publish: the OpenAPI file, the space key, the parent page and the page title. The tool fills in `page-id`, `page-url`, `published-at` and `checksum` after the first publish. The field comments in the file describe every key.

## API token

Create the token at https://id.atlassian.com/manage-profile/security/api-tokens. The tool makes four Confluence v2 calls and no Jira calls, so a scoped token for the Confluence product with these scopes is enough:

| Scope | Why |
|---|---|
| `read:space:confluence` | `GET /wiki/api/v2/spaces?keys=` resolves the space key to a space id. Used only when a page is created. |
| `read:page:confluence` | `GET /wiki/api/v2/pages/{id}` loads the current version number before an update. |
| `write:page:confluence` | `POST /wiki/api/v2/pages` and `PUT /wiki/api/v2/pages/{id}`. |

Classic scope names, if the token form offers those instead: `read:confluence-space.summary`, `read:confluence-content.all`, `write:confluence-content`.

## Publish

```bash
/path/to/confluence-api-doc/bin/confluence-api-doc publish
```

An API whose generated content matches the stored `checksum` is skipped. To force a republish, delete the `checksum` line of that API in `confluence-api-doc.yaml` and run again.
