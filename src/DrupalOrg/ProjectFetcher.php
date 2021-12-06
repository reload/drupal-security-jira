<?php

namespace GithubDrupalSecurityJira\DrupalOrg;

use Symfony\Contracts\HttpClient\HttpClientInterface;

use function VeeWee\Xml\Encoding\xml_decode;

class ProjectFetcher
{

    public function __construct(
        private HttpClientInterface $client,
    ) {
    }

    public function hasReleaseHistory(string $project, string $drupalVersion): bool
    {
        $data = $this->getReleaseHistory($project, $drupalVersion);
        return !isset($data['error']) || !str_contains($data['error'], "No release history");
    }

    /**
     * @return string[]
     */
    public function getSecureVersions(string $project, string $drupalVersion): array
    {
        $data = $this->getReleaseHistory($project, $drupalVersion);
        if (isset($data['error'])) {
            throw new \RuntimeException($data['error']);
        }

        $releases = isset($data['project']['releases']['release']) ?
            $this->xmlDataToArray($data['project']['releases']['release']) :
            [];

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
        return $secureVersion->getNextVersion($version);
    }

    /**
     * @return mixed[]
     */
    private function getReleaseHistory(string $project, string $drupalVersion): array
    {
        $response = $this->client->request(
            "GET",
            "https://updates.drupal.org/release-history/{$project}/all"
        );
        return xml_decode($response->getContent());
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
