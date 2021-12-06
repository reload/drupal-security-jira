<?php

declare(strict_types=1);

namespace DrupalSecurityJira\SiteStatus;

use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Safe\json_decode;

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
        $url = "https://{$this->host}/admin/reports/system_status/{$this->token}";
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
