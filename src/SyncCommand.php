<?php

declare(strict_types=1);

namespace GithubDrupalSecurityJira;

use GithubDrupalSecurityJira\SiteStatus\Fetcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

class SyncCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addArgument(
                "host",
                InputArgument::REQUIRED,
            )
            ->addArgument(
                "token",
                InputArgument::REQUIRED,
            )
            ->addArgument(
                "key",
                InputArgument::OPTIONAL,
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);

        $fetcher = new Fetcher(
            HttpClient::create(),
            $input->getArgument("host"),
            $input->getArgument("token"),
            $input->getArgument("key")
        );
        $data = $fetcher->fetch();

        $logger->debug(print_r($data->getProjectVersionMap(), true));

        return self::SUCCESS;
    }
}
