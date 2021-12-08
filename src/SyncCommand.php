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
        $env->required(['DRUPAL_HOST', 'SYSTEM_STATUS_TOKEN', 'SYSTEM_STATUS_KEY'])->notEmpty();
        $env->ifPresent('DRY_RUN')->isBoolean();

        $host = getenv('DRUPAL_HOST', true) ?: '';
        $token = getenv('SYSTEM_STATUS_TOKEN', true) ?: '';
        $key = getenv('SYSTEM_STATUS_KEY', true) ?: '';
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

        $projectFetcher = new ProjectFetcher(HttpClient::create());

        $insecureProjectVersionMap = array_filter(
            $projectVersionMap,
            function (string $version, string $project) use ($projectFetcher, $data): bool {
                if (!$projectFetcher->hasReleaseHistory($project, $data->getDrupalVersion())) {
                    return false;
                }

                return !$projectFetcher->isSecure($project, $version, $data->getDrupalVersion());
            },
            ARRAY_FILTER_USE_BOTH
        );
        $logger->info(
            "Identified insecure projects: {projects}",
            ['projects' => print_r($insecureProjectVersionMap, true)]
        );

        $securedProjectsVersionMap = $insecureProjectVersionMap;
        array_walk(
            $securedProjectsVersionMap,
            function (string $version, string $project) use ($projectFetcher, $data): string {
                return $projectFetcher->getSecureVersion($project, $version, $data->getDrupalVersion());
            }
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
