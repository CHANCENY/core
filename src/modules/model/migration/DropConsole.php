<?php

namespace Simp\Core\modules\model\migration;

use DI\DependencyException;
use DI\NotFoundException;
use ReflectionException;
use Simp\Core\modules\services\Service;
use Simp\Modal\ModalDefinitions\ModalConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'modal:drop',
    description: 'Remove all tables',
    help: 'This command allows you to delete all tables',
)]
class DropConsole extends Command
{
    protected MigrationCommand $migrationCommand;

    /**
     * @throws ReflectionException
     */
    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        $this->migrationCommand = new MigrationCommand(
            new ModalConfiguration(

                ModalConfig::path()

            )
        );
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $results = $this->migrationCommand->doDrop(
            Service::get('connection')
        );

        foreach ($results as $status) {

            $output->writeln($status);
            
        }

        return Command::SUCCESS;
    }

}