<?php

namespace Simp\Core\extends\form_builder\src\Plugin;

use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\logger\ErrorLogger;
use Simp\Default\BasicField;
use Symfony\Component\Yaml\Yaml;

class FormConfigManager
{
    protected SystemDirectory $systemDirectory;
    protected string $forms_dir;
    public function __construct()
    {
        $this->systemDirectory = new SystemDirectory();
        $this->forms_dir = $this->systemDirectory->setting_dir . DIRECTORY_SEPARATOR . 'forms';
        if (!is_dir($this->forms_dir)) {
            @mkdir($this->forms_dir, 0777, true);
        }
    }

    public function createForm(string $name, array $config): bool
    {
        // remove special characters
        $name = preg_replace('/[^a-zA-Z0-9]/', '_', $name);

        // remove leading and trailing underscores
        $name = trim($name, '_');

        // remove duplicate underscores
        $name = preg_replace('/_+/', '_', $name);
        $name = strtolower($name);

        $file = $this->forms_dir . DIRECTORY_SEPARATOR . $name . '.yml';

        foreach ($config['fields'] as $key => $field) {

            //remove //// replace with \
           // $config['fields'][$key]['handler'] = str_replace('//', '\\', $field['handler'] ?? BasicField::class);
        }

        $config['name'] = $name;
        $config['attributes']['id'] = $name;

        if (empty(file_put_contents($file, Yaml::dump($config, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)))) {
            return false;
        }
        $this->dbActions($name, $config['fields']);
        return true;
    }

    public function getForm(string $name): array
    {
        $file = $this->forms_dir . DIRECTORY_SEPARATOR . $name . '.yml';
        if (!file_exists($file)) {
            return [];
        }
        return Yaml::parseFile($file);
    }

    public function deleteForm(string $name): bool
    {
        $file = $this->forms_dir . DIRECTORY_SEPARATOR . $name . '.yml';
        if (!file_exists($file)) {
            return false;
        }
        $this->dbActions($name,Yaml::parseFile($file)['fields'] ?? [],3);
        return unlink($file);
    }

    public function updateForm(string $name, array $config): bool
    {
        $file = $this->forms_dir . DIRECTORY_SEPARATOR . $name . '.yml';
        if (!file_exists($file)) {
            return false;
        }
        $this->dbActions($name, $config['fields'], 2);
        return file_put_contents($file, Yaml::dump($config, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)) !== false;
    }

    public function getForms(): array
    {
        $files = glob($this->forms_dir . DIRECTORY_SEPARATOR . '*.yml');
        $forms = [];
        foreach ($files as $file) {
            $forms[] = Yaml::parseFile($file);
        }
        return $forms;
    }

    protected function createStorages(string $form_name, array $fields): void
    {
        $create_tables = [];

        try{

            foreach ($fields as $field) {
                $table_name = "forms__".$form_name."_". $field['name'];
                $query = "CREATE TABLE IF NOT EXISTS `{$table_name}` ";

                if ($field['type'] === 'checkbox' || $field['type'] === 'radio' || $field['type'] === 'select') {
                    $query .= "(`id` INT(11) AUTO_INCREMENT NOT NULL, `sid` int(11) NOT NULL, `value` VARCHAR(255) NULL, PRIMARY KEY (`id`))";
                }
                elseif ($field['type'] === 'number') {
                    $query .= "(`id` INT(11) AUTO_INCREMENT NOT NULL,  `sid` int(11) NOT NULL, `value` INT(11) NULL, PRIMARY KEY (`id`))";
                }
                elseif ($field['type'] === 'file') {
                    $query .= "(`id` INT(11) AUTO_INCREMENT NOT NULL,  `sid` int(11) NOT NULL, `value` INT(11) NULL, PRIMARY KEY (`id`))";
                }
                elseif ($field['type'] === 'textarea') {
                    $query .= "(`id` INT(11) AUTO_INCREMENT NOT NULL,  `sid` int(11) NOT NULL, `value` LONGTEXT NULL, PRIMARY KEY (`id`))";
                }
                else {
                    $query .= "(`id` INT(11) AUTO_INCREMENT NOT NULL,  `sid` int(11) NOT NULL, `value` VARCHAR(500) NULL, PRIMARY KEY (`id`))";
                }

                $create_tables[] = $query;
            }

            if (!empty($create_tables)) {
                foreach ($create_tables as $query) {
                    Database::database()->con()->exec($query);
                }
            }
        }catch (\Throwable $exception) {
            ErrorLogger::logger()->logError($exception);
        }

    }

    /**
     *
     */
    protected function deleteStorage(string $name, array $fields): void
    {
        try{
            foreach ($fields as $field) {
                $table_name = "forms__".$name."_". $field['name'];
                Database::database()->con()->exec("DROP TABLE IF EXISTS `{$table_name}`");
            }
        }catch (\Throwable $e) {
            ErrorLogger::logger()->logError($e);
        }
    }

    /**
     * @param string $form_name
     * @param array $fields
     * @param int $action 1 create, 2 update, 3 delete
     * @return void
     */
    protected function dbActions(string $form_name, array $fields, int $action = 1): void
    {
        if ($action === 1 || $action === 2) {
            $this->createStorages($form_name, $fields);
        }

        if ($action === 3) {
            $this->deleteStorage($form_name, $fields);
        }
    }

    public static function factory(): FormConfigManager {
        return new FormConfigManager();
    }
}