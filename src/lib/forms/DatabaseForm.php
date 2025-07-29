<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\messager\Messager;
use Simp\Fields\FieldBase;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Yaml\Yaml;

class DatabaseForm extends FormBase
{
    protected bool $validated = true;
    public function getFormId(): string
    {
        return "database_form";
    }

    public function buildForm(array &$form): array
    {
        $form_file = Caching::init()->get('default.admin.database.form');
        if (file_exists($form_file)) {
            $form = Yaml::parseFile($form_file)['fields']?? [];
        }
        return $form;
    }

    public function validateForm(array $form): void
    {
        // Validate field "host"
        $host = $form['host_name'] ?? null;
        if (is_null($host)) {
            $this->validated = false;
        }
        elseif ($host instanceof FieldBase) {
            if ($host->getRequired() === 'required' && empty($host->getValue())) {
                $this->validated = false;
            }
        }

        // Validate field "user"
        $host = $form['user_name'] ?? null;
        if (is_null($host)) {
            $this->validated = false;
        }
        elseif ($host instanceof FieldBase) {
            if ($host->getRequired() === 'required' && empty($host->getValue())) {
                $this->validated = false;
            }
        }

        // Validate field "database name"
        $host = $form['database_name'] ?? null;
        if (is_null($host)) {
            $this->validated = false;
        }
        elseif ($host instanceof FieldBase) {
            if ($host->getRequired() === 'required' && empty($host->getValue())) {
                $this->validated = false;
            }
        }

    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function submitForm(array &$form): void
    {
        if ($this->validated) {
            $data = [
                'dbname' => $form['database_name']?->getValue(),
                'hostname' => $form['host_name']?->getValue(),
                'username' => $form['user_name']?->getValue(),
                'password' => $form['password']?->getValue(),
                'port' => $form['database_port']?->getValue(),
            ];

            $result = Database::createDatabase($data['dbname'], $data['hostname'], $data['username'],$data['password'], $data['port']);
            if ($result) {
                Messager::toast()->addMessage("Database connection created successfully");
            }
            $schema = Caching::init()->get('default.admin.database');
            if (file_exists($schema)) {
                $schema_data = Yaml::parseFile($schema);
                $schema_data = array_merge($schema_data, $data);
                $system = new SystemDirectory();
                @mkdir($system->setting_dir . DIRECTORY_SEPARATOR . 'database', 0777, true);
                $setting_data = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' .
                    DIRECTORY_SEPARATOR . 'database.yml';
                if (file_put_contents($setting_data, Yaml::dump($schema_data))) {
                    (new RedirectResponse('/'))->send();
                }
            }
        }
    }
}
