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

    protected array $schema;

    protected ?Database $database;

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct(mixed $options = [])
    {
        parent::__construct($options);
        $schema = Caching::init()->get('default.admin.database');
        if (file_exists($schema)) {
            $this->schema = Yaml::parseFile($schema);
        }
        $this->database = Database::database();
    }

    public function getFormId(): string
    {
        return "database_form";
    }

    public function buildForm(array $form): array
    {
        $form =  parent::buildForm($form);
        if (!empty($this->schema) && !empty($this->database)) {
            $form['host_name']['default_value'] = $this->database->getHostname() ?? '';
            $form['user_name']['default_value'] = $this->database->getUsername() ?? '';
            $form['database_name']['default_value'] = $this->database->getDbname() ?? '';
            $form['password']['default_value'] = $this->database->getPassword() ?? '';
            $form['database_port']['default_value'] = $this->database->getPort() ?? '';
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
    public function submitForm(array $form): void
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

            if (!empty($this->schema)) {
                $schema_data = array_merge($this->schema, $data);
                $system = new SystemDirectory();
                @mkdir($system->setting_dir . DIRECTORY_SEPARATOR . 'database', 0777, true);
                $setting_data = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' .
                    DIRECTORY_SEPARATOR . 'database.yml';
                $size = file_put_contents($setting_data, Yaml::dump($schema_data));
                if ($size) {
                    Database::prepareSystemTable();
                    (new RedirectResponse('/core/site-config.php'))->send();
                }
            }
        }
    }
}
