<?php

namespace Simp\Core\extends\system\src\Plugin;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'rebuild:core',
    description: 'Core rebuild handling commands.',
    help: 'This command allows you to clear, rebuild and manage cache.',
)]
class SystemCoreRebuildCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        SystemAction::copyInstallers();
        SystemAction::rebuildCore();
        SystemAction::moveModules();
        $output->writeln('Core rebuild completed successfully.');
        return self::SUCCESS;
    }
}