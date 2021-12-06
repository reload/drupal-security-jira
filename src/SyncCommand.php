<?php

declare(strict_types=1);

namespace DrupalSecurityJira;

use DrupalSecurityJira\DrupalOrg\ProjectFetcher;
use DrupalSecurityJira\SiteStatus\Fetcher;
use Reload\JiraSecurityIssue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

class SyncCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addOption(
                "host",
                null,
                InputOption::VALUE_REQUIRED,
            )
            ->addOption(
                "token",
                null,
                InputOption::VALUE_REQUIRED,
            )
            ->addOption(
                "key",
                null,
                InputOption::VALUE_OPTIONAL,
            )
            ->addOption(
                "watchers",
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY
            )
            ->addOption(
                "dry-run",
                null,
                InputOption::VALUE_NONE,
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);

        $host = $input->getOption("host");
        $logger->debug("Fetching data from {$host}");

        $fetcher = new Fetcher(
            HttpClient::create(),
            $host,
            $input->getOption("token"),
            $input->getOption("key")
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

        array_map(function ($project, $version) use ($input, $host, $logger) {
            $watchers = $input->getOption('watchers');

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

            foreach ($watchers as $watcher) {
                $issue->setWatcher($watcher);
            }

            if (!$input->getOption('dry-run')) {
                $issueId = $issue->ensure();
                $logger->info('Created issue {issue_id}', ['issue_id' => $issueId]);
            } else {
                $logger->info('Would create issue {issue}', ['issue' => print_r($issue, true)]);
            }
        }, array_keys($securedProjectsVersionMap), $securedProjectsVersionMap);

        return self::SUCCESS;
    }
}
