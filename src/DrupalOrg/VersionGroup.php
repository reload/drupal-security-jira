<?php

namespace DrupalSecurityJira\DrupalOrg;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;

use function Safe\array_combine;
use function Safe\preg_replace;

class VersionGroup
{

    /**
     * @param string[] $versions
     */
    public function __construct(
        private array $versions
    ) {
    }

    private function normalizeVersion(string $version): string
    {
        return preg_replace('/^\d\.x\-/', '', $version);
    }

    public function getNextVersion(string $currentVersion): string
    {
        $versions = array_combine($this->versions, $this->versions);

        $normalizedVersions = array_map(function (string $version): string {
            return $this->normalizeVersion($version);
        }, $versions);
        $normalizedCurrentVersion = $this->normalizeVersion($currentVersion);

        $nextVersions = array_filter(
            $normalizedVersions,
            function (string $version) use ($normalizedCurrentVersion): bool {
                return Comparator::greaterThanOrEqualTo($version, $normalizedCurrentVersion);
            }
        );

        $nextVersions = Semver::sort($nextVersions);
        $nextVersion = current($nextVersions);
        if (!is_string($nextVersion)) {
            throw new \RuntimeException("Unexpected value for next version $nextVersion.");
        }
        return $nextVersion;
    }
}
