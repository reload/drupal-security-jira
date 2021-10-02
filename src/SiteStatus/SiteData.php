<?php

declare(strict_types=1);

namespace GithubDrupalSecurityJira\SiteStatus;

class SiteData
{

    public function __construct(
        private Data $data
    ) {
    }

    /**
     * @return string[]
     */
    public function getProjectVersionMap(): array
    {
        $groups = ['core', 'contrib', 'theme'];
        $projects = array_merge(... array_map(function ($group) {
            return (array) $this->data->getData()->system_status->{$group};
        }, $groups));

        $projectVersionsMap = array_map(static function (object $project) {
            return $project->version;
        }, $projects);

        return \array_filter($projectVersionsMap);
    }
}
