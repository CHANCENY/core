<?php

namespace Simp\Core\modules\cron;

use Exception;
use Simp\Core\lib\installation\SystemDirectory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'simp:cron',
    description: 'Run cron jobs forcefully.',
    help: 'This command allows you to run cron jobs forcefully',
)]
class CronCommand extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $cron_manager = new Cron;
        $system = new SystemDirectory();

        $executor = $system->webroot_dir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'cron_executor.php';

        $list = $cron_manager->getCrons();

        $queued = [];
        foreach ($list as $name=>$cron){
            $cron = $cron_manager->getCron($name);
            if ($cron instanceof CronHandler) {
                $queued[$name] = $cron;
            }
        }

        if (count($queued) > 0) {
            foreach ($queued as $k=>$cron) {
                $serialized = base64_encode(serialize($cron));
                $command = 'php '.$executor.' "' . $serialized . '" > /dev/null 2>&1 &';
                $output->writeln("$k has started execution");

                exec($command);
            }
        }

        return Command::SUCCESS;
    }
}