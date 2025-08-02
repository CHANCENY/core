<?php

namespace Simp\Core\modules\database;

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\lib\memory\cache\Caching;
use Throwable;
use PDO;
use Medoo\Medoo;
use PDOException;
use RuntimeException; // Added for custom exception
use Symfony\Component\Yaml\Yaml;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\installation\InstallerValidator;

class Database
{
    // Static property to hold the single instance
    private static ?Database $instance = null;

    // Instance property to hold the PDO connection
    private ?SPDO $pdo = null;

    // Store settings for potential reuse (e.g., logger)
    private array $settings = [];

    // Make constructor private to prevent direct instantiation
    private function __construct(
        protected string $hostname,
        protected string $dbname,
        protected string $username,
        protected string $password,
        protected int $port,
        protected string $dsn,
        protected array $cache,
        protected bool $log,
    )
    {
        $this->settings = func_get_args(); // Store constructor args
        $this->settings['log'] = $log; // Ensure log setting is captured correctly

        $dsn_template = $this->dsn; // Keep original template if needed
        $this->dsn = str_replace(
            ['$host', '$port', '$dbname'], // Removed user/pass as they are separate params
            [$this->hostname, $this->port, $this->dbname],
            $dsn_template
        );

        try {
            $this->pdo = new SPDO($this->dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            // PDO::ATTR_PERSISTENT is often problematic in PHP environments (like FPM)
            // Consider removing or making it configurable if issues arise.
            // $this->pdo->setAttribute(PDO::ATTR_PERSISTENT, true);
            $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [SPDOStatement::class, []]);

            if ($this->log === true) {
                $content = "CONNECTION [OK] " . ($this->pdo->getAttribute(PDO::ATTR_SERVER_INFO) ?? 'N/A') . PHP_EOL;
                // Use the instance method for logging if SPD is available
                $this->pdo->log($content);
            }
        } catch (Throwable $exception) {
            // Error handling improvement (Step 002) - Throw exception instead of exit()
            $errorMessage = "Error: database connection failed: " . $exception->getMessage();
            if ($this->log === true) {
                // Log the error before throwing
                self::staticLogger($errorMessage); // Use static logger as $this->pdo might be null
            }
            // Re-throw a more specific exception or the original one
            throw new RuntimeException($errorMessage, $exception->getCode(), $exception);
        }
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserialization of the instance
    public function __wakeup() {
        throw new RuntimeException("Cannot unserialize a singleton.");
    }

    // Public method to get the PDO connection instance
    public function con(): SPDO
    {
        if ($this->pdo === null) {
            // This should ideally not happen if the constructor succeeded
            throw new RuntimeException("Database connection not established.");
        }
        return $this->pdo;
    }

    // The static method that controls the access to the singleton instance.
    public static function database(): ?Database
    {
        if (self::$instance === null) {
            $system = new SystemDirectory();
            $database_setting_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.yml';

            if (file_exists($database_setting_file)) {
                $settings = Yaml::parse(file_get_contents($database_setting_file));
                if (is_array($settings)) {
                    // Use 'self' to call the private constructor
                    self::$instance = new self(...$settings);
                } else {
                    // Handle case where YAML parsing fails or returns non-array
                    self::staticLogger("Error: Failed to parse database settings or invalid format in {$database_setting_file}");
                    // Optionally throw an exception here to
                    // throw new RuntimeException("Invalid database configuration.");
                    return null; // Or handle as appropriate
                }
            } else {
                self::staticLogger("Error: Database configuration file not found at {$database_setting_file}");
                // Optionally, throw an exception
                // throw new RuntimeException("Database configuration file not found.");
                return null; // Configuration missing
            }
        }
        return self::$instance;
    }

    // Static logger method for use when an instance might not exist
    public static function staticLogger(string $logMessage): void
    {
        try {
            $system = new SystemDirectory();
            $settings_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.yml';
            $log_enabled = false;
            if (file_exists($settings_file)) {
                $settings = Yaml::parse(file_get_contents($settings_file));
                if (!empty($settings['log']) && $settings['log'] === true) {
                    $log_enabled = true;
                }
            }

            if ($log_enabled) {
                $log_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.log';
                file_put_contents($log_file, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (Throwable $e) {
            // Fallback if logging itself fails (e.g., permissions)
            error_log("Failed to write to database log: " . $e->getMessage());
        }
    }

    // Kept original logger for compatibility if needed elsewhere, but consider consolidating
    public static function logger(string $log): void
    {
        // This method relies on $GLOBALS['system_store'] which might be less reliable
        // Consider using the staticLogger or instance logger if possible
        $system = $GLOBALS['system_store'] ?? null;
        if($system instanceof InstallerValidator) {
            $settings_path = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.yml';
            if (file_exists($settings_path)) {
                $settings = Yaml::parse(file_get_contents($settings_path));
                if (!empty($settings['log']) && $settings['log'] === true) {
                    $log_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.log';
                    file_put_contents($log_file, $log.PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            }
        }
    }

    // Query builder remains largely the same, uses static settings loading
    public static function queryBuilder(): ?Medoo {
        $system = new SystemDirectory();
        $database_setting = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.yml';
        if (file_exists($database_setting)) {
            $settings = Yaml::parse(file_get_contents($database_setting));
            if (is_array($settings)) {
                return new Medoo([
                    'type' => 'mysql', // Consider making 'type' configurable too
                    'host' => $settings['hostname'],
                    'database' => $settings['dbname'],
                    'username' => $settings['username'],
                    'password' => $settings['password'],
                    'port' => $settings['port'] ?? 3306, // Add port from settings
                    'charset' => 'utf8mb4', // Good default
                    'collation' => 'utf8mb4_unicode_ci', // Good default
                    // Medoo might have its own logging/options
                ]);
            } else {
                self::staticLogger("Error: Failed to parse database settings for Medoo.");
            }
        }
        return null;
    }

    // createDatabase remains a static utility method
    public static function createDatabase(string $dbname, string $host, string $user, string $password, int $port): false|int
    {
        try {
            $dsn = "mysql:host=$host;port=$port";
            $pdo = new PDO($dsn, $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            return $pdo->exec($sql);
        } catch (PDOException $e) {
            self::staticLogger("Error creating database {$dbname}: " . $e->getMessage());
            return false;
        }
    }

    public static function prepareSystemTable(): bool
    {
        $tables = Caching::init()->get('default.admin.built_in_tables');

        $flag = [];
        if (file_exists($tables)) {
            $tables = Yaml::parseFile($tables);
            if (is_array($tables['table'])) {
                foreach ($tables['table'] as $query) {
                   try{ $flag[] = Database::database()->con()->query($query); }catch (\Throwable){}
                }
            }
        }
        $module_handler = ModuleHandler::factory();
        $modules = $module_handler->getModules();
        foreach($modules as $key=>$module) {
            if ($module_handler->isModuleEnabled($key)) {
                $module_install = $module['path'] . DIRECTORY_SEPARATOR . $key. '.install.php';
                if (file_exists($module_install)) {
                    $database_install = $key . '_database_install';
                    require_once $module_install;
                    if (function_exists($database_install)) {
                        $database_install();
                    }
                }
            }
        }
        return !in_array(false, $flag);
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getDbname(): string
    {
        return $this->dbname;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getDsn(): string
    {
        return $this->dsn;
    }

    public function getCache(): array
    {
        return $this->cache;
    }

    public function isLog(): bool
    {
        return $this->log;
    }

}
