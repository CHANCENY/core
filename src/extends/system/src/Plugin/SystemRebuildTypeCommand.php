<?php

namespace Simp\Core\extends\system\src\Plugin;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'rebuild:types',
    description: 'Content types rebuild handling commands.',
    help: 'This command allows you to clear, rebuild and manage cache.',
)]
class SystemRebuildTypeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        SystemAction::persistContentTypes();
        $output->writeln('Content types rebuild completed successfully.');
        return Command::SUCCESS;
    }
}