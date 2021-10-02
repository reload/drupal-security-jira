<?php

declare(strict_types=1);

namespace GithubDrupalSecurityJira\SiteStatus;

class UnencryptedData implements Data
{

    public function __construct(
        private object $data
    ) {
    }

    public function getData(): object
    {
        return $this->data->system_status;
    }
}
