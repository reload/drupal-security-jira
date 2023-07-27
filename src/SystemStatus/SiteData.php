<?php

declare(strict_types=1);

namespace DrupalSecurityJira\SystemStatus;

class SiteData
{
    public function __construct(
        private Data $data
    ) {
    }

    public function getDrupalVersion(): string
    {
        return "{$this->data->getData()->core->drupal->version}";
    }

    /**
     * @return array<string, string|null>
     */
    public function getProjectVersionMap(): array
    {
        $groups = ['core', 'contrib', 'theme'];
        $projects = array_merge(... array_map(function ($group) {
            return (array) $this->data->getData()->{$group};
        }, $groups));

        $projectVersionsMap = array_map(function (object $project) {
            return $project->version;
        }, $projects);

        return $projectVersionsMap;
    }
}
