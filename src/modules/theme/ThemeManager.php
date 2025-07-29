<?php


/**
 * ThemeManager constructor.
 *
 * Initializes theme-related data by scanning the theme directory,
 * parsing information files, and loading Twig templates for the current theme.
 * Caches theme-related data for later retrieval.
 *
 * @throws PhpfastcacheCoreException
 * @throws PhpfastcacheLogicException
 * @throws PhpfastcacheDriverException
 * @throws PhpfastcacheInvalidArgumentException
 */

namespace Simp\Core\modules\theme;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\themes\TwigResolver;
use Symfony\Component\Yaml\Yaml;

class ThemeManager
{
    protected array $themes = [];
    protected ?string $currentTheme = null;
    protected array $current_theme_files = [];
    /**
     * @var mixed|null
     */
    private ?string $currentThemeHomeTemplate = null;

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $system = new SystemDirectory();
        $themes_base = $system->theme_dir;
        
        $files = array_diff(scandir($themes_base) ?? [], ['.', '..']);

        if (!empty($files)) {
            foreach ($files as $file) {

                $full_path = $themes_base . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR .
                    $file.'.info.yml';

                if (file_exists($full_path)) {
                    $content = Yaml::parseFile($full_path);
                    if (!empty($content['name']) && !empty($content['version'])) {
                        $this->themes[$file] = $content;

                        if (!empty($content['default'])) {
                            $this->currentTheme = $file;
                            $this->currentThemeHomeTemplate = $content['home_template'] ?? null;
                            $all_twig_files = array_diff(scandir($themes_base . DIRECTORY_SEPARATOR . $file) ?? [], ['.', '..']);
                            foreach ($all_twig_files as $twig_file) {
                                $full_twig_path = $themes_base . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $twig_file;
                                if (is_dir($full_twig_path)) {
                                    $this->recursive_dir_iterator($full_twig_path);
                                }
                                elseif (file_exists($full_twig_path) && pathinfo($full_twig_path, PATHINFO_EXTENSION) === 'twig') {
                                    $key = $file. '.view.'. pathinfo($twig_file, PATHINFO_FILENAME);
                                    $this->current_theme_files[$key] = new TwigResolver($full_twig_path);
                                }
                            }
                        }

                    }
                }
            }

            if (!empty($this->currentTheme) && !empty($this->current_theme_files)) {

                $theme_keys = Caching::init()->get('system.theme.keys');
                foreach ($this->current_theme_files as $key => $value) {
                    $theme_keys[] = $key;
                    Caching::init()->set($key, $value);
                }
                $theme_keys = array_unique($theme_keys);
                Caching::init()->set('system.theme.keys', $theme_keys);
            }
        }
        $GLOBALS['theme_manager'] = $this;
        
    }

    protected function recursive_dir_iterator($dir): void
    {
        $files = array_diff(scandir($dir) ?? [], ['.', '..']);
        foreach ($files as $file) {
            $full_path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($full_path)) {
                $this->recursive_dir_iterator($full_path);
            }
            elseif (file_exists($full_path) && pathinfo($full_path, PATHINFO_EXTENSION) === 'twig') {
                $key = $this->currentTheme. '.view.'. pathinfo($full_path, PATHINFO_FILENAME);
                $this->current_theme_files[$key] = new TwigResolver($full_path);
            }
        }
    }

    public function getCurrentTheme(): ?string
    {
        return $this->currentTheme;
    }


    public function getCurrentThemeFiles(): array
    {
        return $this->current_theme_files;
    }


    public function setThemes(array $themes): void
    {
        $this->themes = $themes;
    }


    /**
     * Sets the current theme files.
     *
     * This method updates the list of Twig files associated with the current
     * theme. It accepts an array where the keys represent file identifiers
     * and the values contain data about each Twig file.
     *
     * @param string $currentTheme
     */
    public function setCurrentTheme(string $currentTheme): void
    {
        $this->currentTheme = $currentTheme;
    }

    /**
     * @param array $current_theme_files
     */
    public function setCurrentThemeFiles(array $current_theme_files): void
    {
        $this->current_theme_files = $current_theme_files;
    }
    public function getThemes(): array
    {
        return $this->themes;
    }

    public function getCurrentThemeHomeTemplate(): ?string
    {
        return $this->currentThemeHomeTemplate;
    }


    
    public static function manager(): ThemeManager
    {
        if (isset($GLOBALS['theme_manager'])) {
            return $GLOBALS['theme_manager'];
        }
        return new ThemeManager();
    }
}