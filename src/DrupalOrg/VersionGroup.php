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
        return preg_replace('/^\d\.(\d\.)?x\-/', '', $version);
    }

    private function denormalizeVersion(string $normalizedVersion, string $referenceVersion): string
    {
        $normalizedReference = $this->normalizeVersion($referenceVersion);
        // Assume that a normalized version is a stripped down version of a
        // reference and we can denormalize any version by replacing any version
        // into a reference.
        return str_replace($normalizedReference, $normalizedVersion, $referenceVersion);
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
                return Comparator::greaterThan($version, $normalizedCurrentVersion);
            }
        );

        $nextVersions = Semver::rsort($nextVersions);
        $nextVersion = current($nextVersions);
        if (!is_string($nextVersion)) {
            throw new \RuntimeException("Unexpected value for next version $nextVersion.");
        }

        return $this->denormalizeVersion($nextVersion, $currentVersion);
    }
}
