<?php

declare(strict_types=1);

namespace GithubDrupalSecurityJira\SiteStatus;

class McryptEncryptedData implements Data
{

    public function __construct(
        private object $data,
        private string $key
    ) {
    }

    public function getData(): object
    {
        $data = mcrypt_decrypt(\MCRYPT_RIJNDAEL_128, $this->key, $this->data->data, MCRYPT_MODE_CBC);

        return json_decode(\substr($data, 16));
    }
}
