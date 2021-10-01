<?php

declare(strict_types=1);

namespace GithubDrupalSecurityJira;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{

  public function execute(InputInterface $input, OutputInterface $output)
  {
    return  self::SUCCESS;
  }

}
