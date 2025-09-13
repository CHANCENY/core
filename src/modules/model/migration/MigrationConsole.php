<?php

namespace Simp\Core\modules\model\migration;

use ReflectionException;
use Simp\Modal\ModalDefinitions\ModalConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'modal:migration',
    description: 'Create a migrations',
    help: 'This command allows you to create a migration',
)]
class MigrationConsole extends Command
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $results = $this->migrationCommand->createMigration();

        foreach ($results as $status) {

            $output->writeln($status);

        }

        return Command::SUCCESS;
    }

}