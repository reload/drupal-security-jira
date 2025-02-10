<?php

namespace DrupalSecurityJira\DrupalOrg;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VersionGroupTest extends TestCase
{

    /**
     * @return array[]
     */
    public static function versionGroups(): array
    {
        return [
            'Semver versions including given version' => [
                ["1.0.0", "1.1.0", "1.2.0"],
                "1.1.0",
                "1.1.0",
            ],
            'Semver versions without given version' => [
                ["1.0.0", "1.2.0", "1.2.1"],
                "1.1.0",
                "1.2.0",
            ],
            'Allow major release bumps for semver versions' => [
                ["1.0.0", "2.0.0", "2.1.0"],
                "1.1.0",
                "2.0.0",
            ],
            'Drupal Core versions' => [
                ["7.86", "9.3.1"],
                "7.81",
                "7.86",
            ],
            'Fails when unable to determine version' => [
                ["1.0.0"],
                "1.1.0",
                VersionGroup::UNKNOWN_VERSION,
            ],
            'Legacy Drupal versions' => [
                ["7.x-1.0", "7.x-1.1", "7.x-1.2", "7.x-1.3"],
                "7.x-1.1",
                "7.x-1.1",
            ],
            'Project switches from legacy to semver versions' => [
                ["8.x-1.0", "2.0.1", "2.0.2"],
                "8.x-1.1",
                "2.0.1",
            ],
            'Project has mixed Drupal core versions' => [
                ["6.x-2.7", "7.x-2.8", "7.x-2.9"],
                "7.x-2.7",
                "7.x-2.8",
            ],
        ];
    }

    /**
     * @param string[] $versions
     * @param string|null $nextVersion
     *   The expected next version (if any)
     */
    #[DataProvider('versionGroups')]
    public function testGetNextVersion(
        array $versions,
        string $currentVersion,
        ?string $nextVersion
    ): void {
        $versionGroup = new VersionGroup($versions);
        if ($nextVersion) {
            $this->assertEquals($nextVersion, $versionGroup->getNextVersion($currentVersion));
        } else {
            // If no version is expected an exception should be thrown.
            $this->expectException(\RuntimeException::class);
            $versionGroup->getNextVersion($currentVersion);
        }
    }
}
