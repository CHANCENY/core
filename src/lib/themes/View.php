<?php

namespace Simp\Core\lib\themes;

use Throwable;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\logger\ErrorLogger;
use Simp\Core\modules\theme\ThemeManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class View
{
    /**
     * @var Theme|mixed
     */
    protected Theme $theme;

    public function __construct() {
        $this->theme = new Theme();
    }

    /**
     * @param string $view
     * @param array $data
     * @return string
     * @throws LoaderError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(string $view, array $data = []): string {
        $currentTheme = ThemeManager::manager()->getCurrentTheme();
        $view_key = str_starts_with($view, 'default') ? substr($view, 7) : $view;
        $view_key = trim($view_key, '.');
        $override_key = $currentTheme. ".".$view_key;
        $options = [...$this->theme->getOptions(), ...$data];
        try{
            if (Caching::init()->has($override_key)) {
                $view = $override_key;
            }
        }catch (Throwable $e) {
            ErrorLogger::logger()->logError($e->__toString());
        }
        $string = $this->theme->twig->render($view,$options);
        if (CurrentUser::currentUser()?->isIsAdmin()) {
            $string .= "<!-- Current Theme: {$currentTheme} -->";
            $string .= "<!-- override suggestion: {$override_key} -->";
        }

        return $string;
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function view(string $view, array $data = []): string
    {
        return (new self())->render($view, $data);
    }
}