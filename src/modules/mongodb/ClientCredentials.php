<?php

namespace Simp\Core\modules\mongodb;

use Simp\Core\lib\installation\SystemDirectory;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles the saving of client credentials to a designated storage file.
 *
 * This class provides functionality for persisting client credentials to a
 * YAML file within the system's settings directory. If the target directory
 * does not exist, it will be created with the appropriate permissions.
 */
class ClientCredentials
{
    protected string $storage_file;

    /**
     * Initializes the class, sets up the storage file for MongoDB settings.
     *
     * This constructor creates a directory for storing MongoDB configuration
     * files if it does not already exist. It then sets the `$storage_file`
     * property to the path of the configuration file.
     *
     * @return void
     */
    public function __construct()
    {
        $system = new SystemDirectory();
        $storage_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'mongodb';

        if (!is_dir($storage_file)) {
            mkdir($storage_file, 0775, true);
        }

        $storage_file .= DIRECTORY_SEPARATOR . 'mongodb.yml';
        $this->storage_file = $storage_file;
    }

    /**
     * Saves the given credentials to the predefined storage file.
     *
     * This method writes the provided array of credentials to the storage file in YAML format.
     * The result indicates whether the operation was successful or not.
     *
     * @param array $credentials The associative array containing the credentials to be saved.
     * @return false|int The number of bytes written to the file on success, or false on failure.
     */
    public function saveCredentials(array $credentials): false|int
    {
        return file_put_contents($this->storage_file, Yaml::dump($credentials));
    }

    /**
     * Retrieves credentials from the storage file.
     *
     * This method checks for the existence of the storage file and,
     * if present, parses its contents to extract the credentials.
     * If the file does not exist, it returns an empty array.
     *
     * @return array An array of credentials parsed from the storage file, or an empty array if the file does not exist.
     */
    public function getCredentials(): array
    {
        if (!file_exists($this->storage_file)) {
            return [];
        }
        return Yaml::parseFile($this->storage_file);
    }

    /**
     * Creates and returns a new instance of the class as a ClientCredentials object.
     *
     * This static method instantiates the class and returns the resulting instance.
     *
     * @return ClientCredentials A new instance of the class as a ClientCredentials object.
     */
    public static function credentials(): ClientCredentials {
        return new self();
    }
}