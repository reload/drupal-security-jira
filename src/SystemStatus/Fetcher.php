<?php

declare(strict_types=1);

namespace DrupalSecurityJira\SystemStatus;

use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Safe\json_decode;
use function Safe\parse_url;

class Fetcher
{
    public function __construct(
        public HttpClientInterface $client,
        public string $host,
        public string $token,
        public string $key,
    ) {
    }

    public function fetch(): SiteData
    {
        $host = $this->host;

        // Add a URL scheme if none is present.
        if (!is_string(parse_url($host, PHP_URL_SCHEME))) {
            $host = "https://{$host}";
        }

        $url = "{$host}/admin/reports/project-versions/{$this->token}";
        $response = $this->client->request("GET", $url);
        $data = json_decode($response->getContent());

        $projectData = new OpenSSLEncryptedData($data, $this->key);

        return new SiteData($projectData);
    }
}
