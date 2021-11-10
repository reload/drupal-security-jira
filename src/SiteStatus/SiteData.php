<?php

declare(strict_types=1);

namespace GithubDrupalSecurityJira\SiteStatus;

class SiteData
{

    public function __construct(
        private int $drupalVersion,
        private Data $data
    ) {
    }

    public function getDrupalVersion(): string
    {
        return "{$this->drupalVersion}.x";
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

        $projectVersionsMap = array_map(function (object $project) {
            return $project->version;
        }, $projects);

        // System status module may report modules which are a part of Drupal Core and thus not projects in their own
        // sense. Remove these based an the heuristic that core modules will have the same version as Drupal Core.
        $coreVersion = $projectVersionsMap["drupal"];
        $filteredVersionMap = array_filter(
            $projectVersionsMap,
            function ($version, $project) use ($coreVersion) {
                return $project === "drupal" || $version !== $coreVersion;
            },
            ARRAY_FILTER_USE_BOTH
        );

        return array_filter($filteredVersionMap);
    }
}
