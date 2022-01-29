<?php

namespace DrupalSecurityJira\DrupalOrg;

use Symfony\Contracts\HttpClient\HttpClientInterface;

use function VeeWee\Xml\Encoding\xml_decode;

class ProjectFetcher
{

    /**
     * @var array[]
     *   Project release history cache.
     */
    protected $cache = [];

    public function __construct(
        private HttpClientInterface $client,
    ) {
    }

    /**
     * @return mixed[]
     */
    protected function getReleases(string $project, string $drupalVersion): array
    {
        $data = $this->getReleaseHistory($project, $drupalVersion);
        if (isset($data['error'])) {
            throw new \RuntimeException($data['error']);
        }

        $releases = isset($data['project']['releases']['release']) ?
            $this->xmlDataToArray($data['project']['releases']['release']) :
            [];

        return $releases;
    }

    public function hasReleaseHistory(string $project, string $drupalVersion): bool
    {
        $data = $this->getReleaseHistory($project, $drupalVersion);
        return !isset($data['error']) || !str_contains($data['error'], "No release history");
    }

    /**
     * @return string[]
     */
    public function getVersions(string $project, string $drupalVersion): array
    {
        $releases = $this->getReleases($project, $drupalVersion);

        return array_map(
            function (array $release): string {
                return (string) $release['version'];
            },
            $releases
        );
    }

    public function isKnownVersion(string $project, string $version, string $drupalVersion): bool
    {
        return in_array($version, $this->getVersions($project, $drupalVersion));
    }

    /**
     * @return string[]
     */
    public function getSecureVersions(string $project, string $drupalVersion): array
    {
        $releases = $this->getReleases($project, $drupalVersion);

        $secureReleases = array_filter(
            $releases,
            function (array $release): bool {
                $terms = isset($release['terms']['term']) ? $this->xmlDataToArray($release['terms']['term']) : [];
                return array_reduce(
                    $terms,
                    function ($isSecure, array $term): bool {
                        return $isSecure &&
                            (
                                $term &&
                                $term['name'] !== "Release type" ||
                                $term['value'] !== "Insecure"
                            );
                    },
                    true
                );
            }
        );

        return array_map(
            function (array $release): string {
                return (string) $release['version'];
            },
            $secureReleases
        );
    }

    public function isSecure(string $project, string $version, string $drupalVersion): bool
    {
        return in_array($version, $this->getSecureVersions($project, $drupalVersion));
    }

    public function getSecureVersion(string $project, string $version, string $drupalVersion): string
    {
        $secureVersion = new VersionGroup(
            $this->getSecureVersions($project, $drupalVersion)
        );
        return $secureVersion->getNextVersion($version, ($project !== 'drupal'));
    }

    /**
     * @return mixed[]
     */
    private function getReleaseHistory(string $project, string $drupalVersion): array
    {
        $cacheId = "$project-$drupalVersion";
        if (isset($this->cache[$cacheId])) {
            return $this->cache[$cacheId];
        }

        $response = $this->client->request(
            "GET",
            "https://updates.drupal.org/release-history/{$project}/all"
        );
        $data = xml_decode($response->getContent());

        $this->cache[$cacheId] = $data;
        return $data;
    }

    /**
     * Get an array of elements from XML data as reported by xml_decode.
     *
     * If there are multiple subelements then they are simply returned.
     *
     * If there is only one element then that element will be returned in an
     * array.
     *
     * If there are no subelements then an empty array is returned.
     *
     * @param mixed[] $element
     *
     * @return mixed[]
     */
    private function xmlDataToArray(array $element): array
    {
        if (!$element) {
            return [];
        }
        if (!is_array(current($element))) {
            return [$element];
        }

        return $element;
    }
}
