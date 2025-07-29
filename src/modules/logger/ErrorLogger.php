<?php

namespace Simp\Core\modules\logger;

use Monolog\Logger;
use Simp\Core\lib\installation\SystemDirectory;

class ErrorLogger extends SystemDirectory
{
    const LEVEL_INFO = 0;
    const LEVEL_WARNING = 1;
    const LEVEL_ERROR = 2;
    const LEVEL_DEBUG = 3;

    protected array $logs = [];

    public function __construct( bool $read = false)
    {
        parent::__construct();
        if ($read) {
            $error_file = $this->setting_dir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'error.log';
            if (file_exists($error_file)) {
                $content = file_get_contents($error_file);
                $content_list = explode("---------------------", $content);
                $content_list = array_reverse($content_list);
                $content_list = array_filter($content_list, function ($item) {
                    return !empty(trim($item));
                });

                foreach ($content_list as $content) {
                    $errors = trim($content);
                    $errors = explode('--', $errors);
                    $errors = array_map(function ($item) {
                        return trim($item);
                    }, $errors);

                    if (count($errors) >= 3) {
                        $one = [];
                        foreach ($errors as $error) {
                            $line = explode('-:-', $error);
                            if (trim($line[0]) === 'LEVEL') {
                                $value = trim(end($line));
                                $one[$line[0]] = match ((int)$value) {
                                    self::LEVEL_INFO => "INFO",
                                    self::LEVEL_WARNING => "WARNING",
                                    self::LEVEL_ERROR => "ERROR",
                                    self::LEVEL_DEBUG => "DEBUG",
                                    default => "UNKNOWN",
                                };
                            }else {
                                $one[$line[0]] = end($line);
                            }

                        }
                        $this->logs[] = $one;
                    }
                }
            }
        }
    }

    public function logInfo(string $message): void
    {
        $GLOBALS['temp_error_log'][] = [
            'message' => $message,
            'level' => ErrorLogger::LEVEL_INFO,
            'severity' => 'info',
            'created_at' => time(),
        ];
    }
    public function logWarning(string $message): void
    {
        $GLOBALS['temp_error_log'][] = [
            'message' => $message,
            'level' => ErrorLogger::LEVEL_WARNING,
            'severity' => 'warning',
            'created_at' => time(),
        ];
    }

    public function logError(string $message): void
    {
        $GLOBALS['temp_error_log'][] = [
            'message' => $message,
            'level' => ErrorLogger::LEVEL_ERROR,
            'severity' => 'error',
            'created_at' => time(),
        ];
    }
    public function logDebug(string $message): void
    {
        $GLOBALS['temp_error_log'][] = [
            'message' => $message,
            'level' => ErrorLogger::LEVEL_DEBUG,
            'severity' => 'debug',
            'created_at' => time(),
        ];
    }

    public static function logger(): ErrorLogger
    {
        return new self();
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function __destruct()
    {
        $system = new SystemDirectory();
        if(is_dir($system->setting_dir . DIRECTORY_SEPARATOR . 'logs') === false) {
            mkdir($system->setting_dir . DIRECTORY_SEPARATOR . 'logs', 0777, true);
        }
        $log_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'error.log';
        if (!file_exists($log_file)) {
            touch($log_file);
        }
        $content = array_map(function ($item) {
            return "\n---------------------\nLEVEL-:- {$item['level']}--\nSEVERITY-:- {$item['severity']}--\nMESSAGE-:- {$item['message']}--\nCREATED-:- {$item['created_at']}\n---------------------\n";
        }, $GLOBALS['temp_error_log'] ?? []);
        file_put_contents($log_file, implode("\n", $content), FILE_APPEND);
        unset($system);
    }
}