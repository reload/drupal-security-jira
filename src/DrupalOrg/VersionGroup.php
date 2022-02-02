<?php

namespace DrupalSecurityJira\DrupalOrg;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;

use function Safe\preg_match;
use function Safe\preg_replace;

/**
 * A version group represents a set of versions.
 *
 * In this context it will usually be versions of a specific project on
 * Drupal.org.
 *
 * Multiple version schemes are supported:
 *
 * - Semantic versions ({major}.{minor}.{patch}[-(alpha|beta|RC){number}])
 * - Legacy Drupal version numbers ({API compatibility}-{major}.{minor}[-(alpha|beta|RC){number}])
 */
class VersionGroup
{

    public const UNKNOWN_VERSION = 'unknown';

    /**
     * @param string[] $versions
     */
    public function __construct(
        private array $versions
    ) {
    }

    private function normalizeVersion(string $version): string
    {
        return preg_replace('/^\d\.(\d\.)?x\-/', '', $version);
    }

    /**
     * Prepare a map normalized version to real/original versions.
     *
     * A normalized version is a semver version which we can sort in
     * later. Legacy versions (e.g. 7.x-2.1) has the core version
     * removed to form a normalized semver version.
     *
     * Legacy versions not matching the core version of the current
     * version are removed.
     *
     * @param string $currentVersion
     * @param string[] $versions
     *
     * @return array<string,string>
     */
    private function prepareVersions(string $currentVersion, array $versions): array
    {
        $coreVersion = preg_replace('/^(\d\.(\d\.)?x)\-.*/', '\1', $currentVersion);
        $result = [];

        foreach ($versions as $version) {
            // Skip the version if it is a legacy version and it
            // doesn't match the current versions core version.
            if (preg_match('/\.x-/', $version) && !preg_match('/^' . preg_quote($coreVersion, '/') . '/', $version)) {
                continue;
            }

            $result[$this->normalizeVersion($version)] = $version;
        }

        return $result;
    }

    /**
     * Determine which version in this set which most closely matches a given version.
     *
     * This only takes the current and newer versions into account.
     *
     * @param string $currentVersion
     *   The given version.
     *
     * @return string
     *   The closest match to the provided version. If no match could
     *   be found, it returns the string 'unknown'.
     */
    public function getNextVersion(string $currentVersion): string
    {
        $versions = $this->prepareVersions($currentVersion, $this->versions);
        $normalizedCurrentVersion = $this->normalizeVersion($currentVersion);

        $nextVersions = array_filter(
            $versions,
            function (string $version) use ($normalizedCurrentVersion): bool {
                return Comparator::greaterThanOrEqualTo($version, $normalizedCurrentVersion);
            },
            ARRAY_FILTER_USE_KEY,
        );

        $sortedVersions = Semver::sort(array_keys($nextVersions));
        $nextVersion = current($sortedVersions);
        if (!is_string($nextVersion)) {
            return self::UNKNOWN_VERSION;
        }

        return $nextVersions[$nextVersion];
    }
}
