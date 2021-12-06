<?php

declare(strict_types=1);

namespace DrupalSecurityJira\SiteStatus;

use function Safe\json_decode;
use function Safe\substr;

class OpenSSLEncryptedData implements Data
{
    public function __construct(
        private object $data,
        private string $key,
    ) {
    }

    public function getData(): object
    {
        $key = hash("SHA256", $this->key, true);
        // @phpstan-ignore-next-line $key will always be a string
        $data = openssl_decrypt($this->data->data, "AES-128-CBC", $key);
        if (!$data) {
            throw new \RuntimeException('Unable to decrypt data');
        }

        return json_decode(substr($data, 16));
    }
}
