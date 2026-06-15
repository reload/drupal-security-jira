<?php

declare(strict_types=1);

namespace DrupalSecurityJira;

use Dotenv\Dotenv;
use DrupalSecurityJira\DrupalOrg\ProjectFetcher;
use DrupalSecurityJira\SystemStatus\Fetcher;
use Psr\Log\LoggerInterface;
use Reload\JiraSecurityIssue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SyncCommand extends Command
{
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Use create*Unsafe*Immutable() since it is required to support
        // getenv() which is used by reload/jira-security-issue.
        $env = Dotenv::createUnsafeImmutable(__DIR__ . '/../');
        $env->safeLoad();
        $env->required(['DRUPAL_HOST', 'URL_TOKEN', 'ENCRYPTION_KEY'])->notEmpty();
        $env->ifPresent('DRY_RUN')->isBoolean();

        $host = getenv('DRUPAL_HOST', true) ?: '';
        $token = getenv('URL_TOKEN', true) ?: '';
        $key = getenv('ENCRYPTION_KEY', true) ?: '';
        $dry_run = (bool) getenv('DRY_RUN', true);

        $logger = new ConsoleLogger($output);

        $logger->debug("Fetching data from {$host}");
        $fetcher = new Fetcher(
            $this->createHttpClient($logger),
            $host,
            $token,
            $key
        );
        $data = $fetcher->fetch();

        $projectVersionMap = $data->getProjectVersionMap();
        $logger->debug("Retrieved projects: {projects}", ['projects' => print_r($projectVersionMap, true)]);

        // Log projects with no known version.
        $unknownProjects = array_filter($projectVersionMap, 'is_null');
        $logger->notice('Projects with no known version: {projects}', [
            'projects' => print_r(array_keys($unknownProjects), true),
        ]);

        // Filter away projects with no known version.
        $projectVersionMap = array_filter($projectVersionMap);

        $projectFetcher = new ProjectFetcher($this->createHttpClient($logger));

        $filters = [
            "Identify projects on Drupal.org" =>
                function (string $version, string $project) use ($projectFetcher, $data): bool {
                    return $projectFetcher->hasReleaseHistory($project, $data->getDrupalVersion());
                },
            "Identify projects with official versions" =>
                function (string $version, string $project) use ($projectFetcher, $data): bool {
                    return $projectFetcher->isKnownVersion($project, $version, $data->getDrupalVersion());
                },
            "Identify insecure projects" =>
                function (string $version, string $project) use ($projectFetcher, $data): bool {
                    return !$projectFetcher->isSecure($project, $version, $data->getDrupalVersion());
                },
        ];

        foreach ($filters as $description => $filter) {
            $projectVersionMap = array_filter($projectVersionMap, $filter, ARRAY_FILTER_USE_BOTH);
            $logger->notice(
                "{description}: {projects}",
                [
                    'description' => $description,
                    'projects' => print_r($projectVersionMap, true)
                ]
            );
        }

        $securedProjectsVersionMap = $projectVersionMap;
        array_walk(
            $securedProjectsVersionMap,
            function (string &$version, string $project) use ($projectFetcher, $data): void {
                $version = $projectFetcher->getSecureVersion($project, $version, $data->getDrupalVersion());
            }
        );

        $logger->info(
            'Identified security updates {projects}',
            ['projects' => print_r($securedProjectsVersionMap, true)]
        );

        array_map(function ($project, $version) use ($host, $logger, $dry_run) {
            // phpcs:disable Generic.Files.LineLength.TooLong
            $body = <<<EOT
- Site: [{$host}]
- Sikkerhedsopdatering: [{$project}|https://drupal.org/project/{$version}] version [{$version}|https://www.drupal.org/project/{$project}/releases/{$version}]
EOT;
            // phpcs:enable Generic.Files.LineLength.TooLong

            $issue = (new JiraSecurityIssue())
                ->setKeyLabel($host)
                ->setKeyLabel($project)
                ->setKeyLabel("{$project}:{$version}")
                ->setTitle("{$project} ({$version})")
                ->setBody($body);

            if (!$dry_run) {
                $issueId = $issue->ensure();
                $logger->info('Created issue {issue_id}', ['issue_id' => $issueId]);
            } else {
                $logger->info('Would create issue {issue}', ['issue' => print_r($issue, true)]);
            }
        }, array_keys($securedProjectsVersionMap), $securedProjectsVersionMap);

        return self::SUCCESS;
    }

    /**
     * Create an HTTP client that retries failed requests with exponential
     * backoff, retrying up to 3 times before giving up.
     */
    private function createHttpClient(LoggerInterface $logger): HttpClientInterface
    {
        // GenericRetryStrategy defaults to a 1000ms initial delay and a
        // multiplier of 2.0, i.e. exponential backoff (1s, 2s, 4s, ...).
        $strategy = new GenericRetryStrategy();

        return new RetryableHttpClient(HttpClient::create(), $strategy, 3, $logger);
    }
}
