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

        // from string $view remove default.view
        $override_key = trim(str_replace('default.view.','',$view));

        $suggestions = $this->suggestTwigTemplates($override_key);

        foreach ($suggestions as $suggestion) {

            // from string $suggestion remove .html.twig
            $purified_suggestion = trim(str_replace('.html.twig','',$suggestion));
            $normalized_suggestion = !empty($currentTheme) ? $currentTheme. '.view.'. $purified_suggestion : $purified_suggestion;
            if (Caching::init()->has($normalized_suggestion)) {
                $view = $normalized_suggestion;
                break;
            }
        }

        $options = [...$this->theme->getOptions(), ...$data];
        $string = $this->theme->twig->render($view,$options);
        $currentTheme = empty($currentTheme) ? 'default' : $currentTheme;
        if (CurrentUser::currentUser()?->isIsAdmin()) {
            $string .= "<!-- Current Theme: {$currentTheme} \n";
            $string .= "used:  {$view}\n";
            $string .= "suggestions: \n";
            $string .= "------------------------------------\n";
            $string .= "Please create one in your theme from the following \n\n";
            $string .= implode("\n", $suggestions);

            $string .= "\n------------------------------------\n\n";
            $string .= "Note: To use the template alway prrefix with theme name them .view. then template name without .twig \n";
            $string .= "-->";
        }

        return $string;
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
    public static function view(string $view, array $data = []): string
    {
        $data = ['options'=>$data,...$data];
        return (new self())->render($view, $data);
    }


    function suggestTwigTemplates(string $baseName, int $count = 10): array
    {
        // Normalize name
        $name = strtolower(trim($baseName));

        // Split by both . and _
        $parts = preg_split('/[._]+/', $name);

        $suggestions = [];

        // 1. Full base name, dot style
        $suggestions[] = implode('.', $parts) . '.html.twig';

        // 2. Full base name, underscore style
        $suggestions[] = implode('_', $parts) . '.html.twig';

        // 3. Progressive dotted prefixes
        for ($i = 1; $i <= count($parts); $i++) {
            $suggestions[] = implode('.', array_slice($parts, 0, $i)) . '.html.twig';
        }

        // 4. Progressive underscored prefixes
        for ($i = 1; $i <= count($parts); $i++) {
            $suggestions[] = implode('_', array_slice($parts, 0, $i)) . '.html.twig';
        }

        // 5. Reversed order
        $suggestions[] = implode('.', array_reverse($parts)) . '.html.twig';
        $suggestions[] = implode('_', array_reverse($parts)) . '.html.twig';

        // Make unique & trim to requested count
        $suggestions = array_values(array_unique($suggestions));
        return array_slice($suggestions, 0, $count);
    }

}