<?php

namespace Simp\Core\extends\system\src\Plugin;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:clear',
    description: 'Cache handling commands.',
    help: 'This command allows you to clear, rebuild and manage cache.',
)]
class SystemCommand extends Command
{
   protected function execute(InputInterface $input, OutputInterface $output): int
   {
       SystemAction::clearCache();
       $output->writeln('Cache cleared successfully.');
       return Command::SUCCESS;
   }
}