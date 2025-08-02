<?php

namespace Simp\Core\modules\cron;


use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\database\Database;
use Symfony\Component\Yaml\Yaml;

class Cron
{
    protected array $jobs = [];
    protected array $subscribers = [];

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $default_cron = Caching::init()->get('default.admin.cron.jobs') ?? [];
        if (!empty($default_cron) && \file_exists($default_cron)) {
            $this->jobs = Yaml::parseFile($default_cron) ?? [];
        }
        $system = new SystemDirectory;
        $custom_cron = $system->setting_dir . \DIRECTORY_SEPARATOR . 'cron' . \DIRECTORY_SEPARATOR . 'custom_cron.yml';
        if (!\is_dir( $system->setting_dir . \DIRECTORY_SEPARATOR . 'cron' )) {
            @\mkdir( $system->setting_dir . \DIRECTORY_SEPARATOR . 'cron', 0777, true);
        }
        if (!\file_exists($custom_cron)) {
            @\touch($custom_cron);
        }
        $custom = Yaml::parseFile($custom_cron) ?? [];
        $this->jobs = [...$this->jobs, ...$custom];
        $subscribers = Caching::init()->get('default.admin.cron.subscriber') ?? [];
        if (!empty($subscribers) && \file_exists($subscribers)) {
            $this->subscribers = Yaml::parseFile($subscribers) ?? [];
        }

        $custom_subscribers = $system->setting_dir . \DIRECTORY_SEPARATOR . 'cron' . \DIRECTORY_SEPARATOR . 'custom_subscribers.yml';
        if (!\file_exists($custom_subscribers)) {
            @\touch($custom_subscribers);
        }
        $custom = Yaml::parseFile($custom_subscribers) ?? [];
        $this->subscribers = [...$this->subscribers, ...$custom];

        // Bring cron defined in modules
        $module_handler = ModuleHandler::factory();
        $modules = $module_handler->getModules();
        foreach ($modules as $key=>$module) {
            $install_module = $module['path']. DIRECTORY_SEPARATOR . $key. '.install.php';
            if (file_exists($install_module) && !empty($module['enabled'])) {
                require_once $install_module;
                $cron_subscriber = $key. '_cron_subscribers_install';
                $cron_job = $key. '_cron_jobs_install';
                if (function_exists($cron_subscriber)) {
                    $this->subscribers = array_merge($this->subscribers, $cron_subscriber());
                }
                if (function_exists($cron_job)) {
                    $this->jobs = array_merge($this->jobs, $cron_job());
                }
            }
        }

    }

    public function getCrons(): array {
        return $this->jobs;
    }

    public function add(string $name, array $data): bool|int
    {
        $this->jobs[$name] = $data;
         $system = new SystemDirectory;
        $custom_cron = $system->setting_dir . \DIRECTORY_SEPARATOR . 'cron' . \DIRECTORY_SEPARATOR . 'custom_cron.yml';
        if (\file_exists($custom_cron)) {
            $d = Yaml::dump($this->jobs, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            return \file_put_contents($custom_cron, $d);
        }
        return false;
    }

    public function getSubscribers(): array
    {
        $keys = array_keys($this->subscribers);

        return array_combine($keys, $keys);

    }

    public function getCron(string $name): ?CronHandler
    {
        $cron = $this->jobs[$name] ?? null;
        if ($cron) {
            $class = $this->subscribers[$cron['subscribers']] ?? null;
            if ($class) {
                $cron['name'] = $name;
                $cron['subscribers'] = new $class;
            }
            return new CronHandler(...$cron);
        }
        return null;
    }

    public function getScheduledCrons(): array
    {
        $keys = array_keys($this->jobs);
        $query = "SELECT * FROM cron_jobs WHERE name IN ('" . implode("','", $keys) . "')";
        $query = Database::database()->con()->prepare($query);
        $query->execute();
        return $query->fetchAll();
    }

    public function getCronLogs(): array
    {
        $query = "SELECT * FROM simp_cron_logs ORDER BY created_at DESC LIMIT 100";
        $query = Database::database()->con()->prepare($query);
        $query->execute();
        return $query->fetchAll();
    }

    public function getCronScriptFile(): array
    {
        $system = new SystemDirectory;
        $root = getcwd();
        $script = $system->root_dir . DIRECTORY_SEPARATOR .'vendor' . DIRECTORY_SEPARATOR .
            'bin' . DIRECTORY_SEPARATOR . 'cron.php';

        $script2 = $system->root_dir . DIRECTORY_SEPARATOR .'vendor' . DIRECTORY_SEPARATOR .
            'bin' . DIRECTORY_SEPARATOR . 'cron';

        $runnable = [];

        if (\file_exists($script)) {
            $runnable[] = $script;
        }
        if (\file_exists($script2)) {
            $runnable[] = $script2;
        }


        return $runnable;
    }

    public static function factory(): Cron {
        return new self();
    }
}
