<?php

declare(strict_types=1);

namespace DrupalSecurityJira;

use Dotenv\Dotenv;
use DrupalSecurityJira\DrupalOrg\ProjectFetcher;
use DrupalSecurityJira\SystemStatus\Fetcher;
use Reload\JiraSecurityIssue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

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
            HttpClient::create(),
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

        $projectFetcher = new ProjectFetcher(HttpClient::create());

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
}
