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
        public ?string $key,
    ) {
    }

    public function fetch(): SiteData
    {
        $host = $this->host;

        // Add a URL scheme if none is present.
        if (!is_string(parse_url($host, PHP_URL_SCHEME))) {
            $host = "https://{$host}";
        }

        $url = "{$host}/admin/reports/system_status/{$this->token}";
        $response = $this->client->request("GET", $url);
        $data = json_decode($response->getContent());

        $encryptedTypes = ["encrypted", "encrypted_openssl"];
        $key = $this->key;
        if (in_array($data->system_status, $encryptedTypes) && !$key) {
            throw new \InvalidArgumentException(
                "Encryption key is required to system status type {$data->system_status}"
            );
        }

        // @var string $key
        $projectData = match ($data->system_status) {
            // @phpstan-ignore-next-line $key will always be a string
            "encrypted_openssl" => new OpenSSLEncryptedData($data, $key),
            // @phpstan-ignore-next-line $key will always be a string
            "encrypted" => new McryptEncryptedData($data, $key),
            default => new UnencryptedData($data)
        };

        return new SiteData((int) $data->drupal_version, $projectData);
    }
}
