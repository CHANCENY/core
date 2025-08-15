<?php

namespace Simp\Core\modules\logger;

use Monolog\Logger;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\themes\View;

class ErrorLogger extends SystemDirectory
{
    protected array $logs = [];

    public function __construct( bool $read = false)
    {
        parent::__construct();
        if ($read) {
            $error_file = $this->private_dir . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($error_file)) {
                mkdir($error_file, 0777, true);
            }
            $list = array_diff(scandir($error_file) ?? [], ['.', '..']);

            // Sort: newest created file first
            usort($list, function($a, $b) use ($error_file) {
                return filectime($error_file . DIRECTORY_SEPARATOR . $b)
                    <=> filectime($error_file . DIRECTORY_SEPARATOR . $a);
            });

            foreach ($list as $file) {
                $path = $error_file . DIRECTORY_SEPARATOR . $file;
                if (file_exists($path)) {
                    $this->logs[] = file_get_contents($path);
                }
            }
        }
    }

    public function logInfo(\Throwable $message): void
    {
        $GLOBALS['temp_error_log'][] = [
            'status_code' => $message->getCode(),
            'status_text' => "Notice",
            'exception' => [
                'message' => $message->getMessage(),
                'file'    => $message->getFile(),
                'line'    => $message->getLine(),
                'class'   => get_class($message),
                'trace'   => $message->getTrace()
            ],
            'page_title' => $message->getCode() ." - ".get_class($message)
        ];
    }
    public function logWarning(\Throwable $message): void
    {
        $GLOBALS['temp_error_log'][] = [
            'status_code' => $message->getCode(),
            'status_text' => "Warning",
            'exception' => [
                'message' => $message->getMessage(),
                'file'    => $message->getFile(),
                'line'    => $message->getLine(),
                'class'   => get_class($message),
                'trace'   => $message->getTrace()
            ],
            'page_title' => $message->getCode() ." - ".get_class($message)
        ];
    }

    public function logError(\Throwable $message): void
    {
        $GLOBALS['temp_error_log'][] = [
            'status_code' => $message->getCode(),
            'status_text' => "Error",
            'exception' => [
                'message' => $message->getMessage(),
                'file'    => $message->getFile(),
                'line'    => $message->getLine(),
                'class'   => get_class($message),
                'trace'   => $message->getTrace()
            ],
            'page_title' => $message->getCode() ." - ".get_class($message)
        ];
    }
    public function logDebug(\Throwable $message): void
    {
        $GLOBALS['temp_error_log'][] = [
            'status_code' => $message->getCode(),
            'status_text' => "Bug Error",
            'exception' => [
                'message' => $message->getMessage(),
                'file'    => $message->getFile(),
                'line'    => $message->getLine(),
                'class'   => get_class($message),
                'trace'   => $message->getTrace()
            ],
            'page_title' => $message->getCode() ." - ".get_class($message)
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
        if(is_dir($system->private_dir . DIRECTORY_SEPARATOR . 'logs') === false) {
            mkdir($system->private_dir . DIRECTORY_SEPARATOR . 'logs', 0777, true);
        }
        $log_file = $system->private_dir . DIRECTORY_SEPARATOR . 'logs';

        $content = array_map(function ($item) {
            return View::view('default.view.system.error', $item);
        }, $GLOBALS['temp_error_log'] ?? []);

        foreach ($content as $item) {

            // generate random file name .twig
            $file_name = bin2hex(random_bytes(16));
            $file_name .= ".twig";

            $full_file = $log_file . DIRECTORY_SEPARATOR . $file_name;
            file_put_contents($full_file, $item);
        }

        file_put_contents($log_file, implode("\n", $content), FILE_APPEND);
        unset($system);
    }
}