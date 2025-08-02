<?php

namespace Simp\Core\modules\config;



use Simp\Core\lib\installation\SystemDirectory;

class SettingsWriter
{
    protected string $filePath;
    protected array $settings;

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $system = new SystemDirectory();
        $this->filePath = $system->webroot_dir. DIRECTORY_SEPARATOR . 'sites'. DIRECTORY_SEPARATOR . 'settings.php';
        $this->settings = $GLOBALS['settings'];
    }

    /**
     * Writes the settings to the file.
     *
     * @return bool
     */
    public function write(): bool
    {
        $phpCode = $this->generatePhpCode();
        return !empty(file_put_contents($this->filePath, $phpCode));
    }

    /**
     * Generates the full PHP code to write into the file.
     *
     * @return string
     */
    protected function generatePhpCode(): string
    {
        $useStatements = <<<PHP
<?php

/**
 * Simple CMS settings file: All the variables included here will be globally.
 */

PHP;

        $arrayExport = var_export($this->settings, true);

        // Optional: indent for readability
        $arrayExportIndented = preg_replace("/^/m", '    ', $arrayExport);

        return $useStatements . "\n\n" . '$GLOBALS[\'settings\'] = ' . $arrayExportIndented . ";\n";
    }

    public static function settingsWriter(): SettingsWriter
    {
        return new self();
    }
}
