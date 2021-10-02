<?php

namespace GithubDrupalSecurityJira\DrupalOrg;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use function VeeWee\Xml\Encoding\xml_decode;

class ProjectFetcher
{

    public function __construct(
        private HttpClientInterface $client,
    ) {}

    public function hasReleaseHistory(string $project) {
        $data = $this->getReleaseHistory($project);
        return !isset($data['error']) || !str_contains($data['error'], "No release history");
    }

    public function getSecureVersions(string $project): array {
        $data = $this->getReleaseHistory($project);
        if (isset($data['error'])) {
            throw new \RuntimeException($data['error']);
        }

        $releases = isset($data['project']['releases']['release']) ? $this->xmlDataToArray($data['project']['releases']['release']) : [];

        $secureReleases = array_filter(
            $releases,
            function (array $release) {
                $terms = isset($release['terms']['term']) ? $this->xmlDataToArray($release['terms']['term']) : [];
                return array_reduce(
                    $terms,
                    function ($isSecure, array $term) {
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

        $secureVersions = array_map(
            function (array $release) {
                return $release['version'];
            },
            $secureReleases
        );

        return $secureVersions;
    }

    private function getReleaseHistory(string $project): array {
        $response = $this->client->request("GET", "https://updates.drupal.org/release-history/{$project}/current");
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
     */
    private function xmlDataToArray(array $element): array {
        if (!$element) {
            return [];
        }
        if (!is_array(current($element))) {
            return [$element];
        }

        return $element;
    }

}
