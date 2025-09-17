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
use Simp\Core\components\request\Request;
use Simp\Core\extends\multi_site_support\src\Plugin\MultiSiteSupport;
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

        // handle multi-site themes
        $request = Request::createFromGlobals();
        $domain = $request->getHttpHost();

        $multi_site_support = new MultiSiteSupport();

        $this->themes = $multi_site_support->getThemes();
        $this->currentTheme = $multi_site_support->getCurrentTheme();
        $this->currentThemeHomeTemplate = $multi_site_support->getCurrentThemeHomeTemplate();
        $this->current_theme_files = $multi_site_support->getCurrentThemeFiles();

        if (MultiSiteSupport::isMultiSiteSupportEnabled()) {
            $theme_used = $multi_site_support->getThemeByDomain($domain);

            if ($theme_used) {

                $this->currentTheme = $multi_site_support->getThemeIdByDomain($domain);
                $this->current_theme_files = $multi_site_support->getCurrentThemeFiles();
                $this->currentThemeHomeTemplate = $theme_used['home_template'] ?? 'default.view.home';
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

    public function getTheme(mixed $theme)
    {
        return $this->themes[$theme] ?? [];
    }
}