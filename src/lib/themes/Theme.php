<?php

namespace Simp\Core\lib\themes;

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\components\request\Request;
use Simp\Core\modules\menu\Menus;
use Twig\Loader\ArrayLoader;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\assets_manager\AssetsManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Simp\Core\modules\services\Service;

class Theme extends SystemDirectory
{
    protected array $twig_functions;
    protected array $twig_filters;
    protected string $twig_function_definition_file;
    protected string $twig_filter_definition_file;
    protected array $options;
    public readonly Environment $twig;
    protected array $extra_extensions;

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        parent::__construct();
        $this->extra_extensions = [];
        $default_twig_function = Caching::init()->get('default.admin.functions');
        if (file_exists($default_twig_function)) {
            require_once $default_twig_function;
            $this->twig_functions = get_functions();
        }

        $this->twig_function_definition_file = $this->webroot_dir .DIRECTORY_SEPARATOR . 'twig'.DIRECTORY_SEPARATOR.'functions.php';
        if (file_exists($this->twig_function_definition_file)) {
            require_once $this->twig_function_definition_file;
            $custom_functions = get_functions();
            $this->twig_functions = [...$this->twig_functions, ... $custom_functions];
        }

        // Loading default filters.
        $default_filters_function = Caching::init()->get('default.admin.filters');
        $default_filters_function_array = [];
        if (file_exists($default_filters_function ?? '')) {
            require_once $default_filters_function;
            $this->twig_filters = get_filters();
        }

        // Loading custom filters.
        $this->twig_filter_definition_file = $this->webroot_dir .DIRECTORY_SEPARATOR . 'twig'.DIRECTORY_SEPARATOR.'filters.php';
        if(file_exists($this->twig_filter_definition_file)) {
            require_once $this->twig_filter_definition_file;
            $custom_filters = get_filters();
            $this->twig_filters = [...$this->twig_filters, ... $custom_filters];
        }

        /**@var Request $request**/
        $request = Service::serviceManager()->request;
        $menus = Menus::menus();

        $assets_manager = new AssetsManager();
        $this->options = [
            'page_title' => $request->server->get('ROUTE_ATTRIBUTES')['route']->route_title ?? 'Simp CMS',
            'page_description' => "",
            'page_keywords' => 'Content, Management, System',
            'request' => [
                'user' => CurrentUser::currentUser(),
                'http' => $request,
            ],
            'assets' => $assets_manager,
            'theme' => [
                'admin' => [
                    'admin_assets_head' => $assets_manager->adminHeadAssets(),
                    'admin_assets_footer' => $assets_manager->adminFooterAssets(),
                    'navigation'=> $assets_manager->adminNavigation(),
                ],
                'assets' => [
                    'head' => $GLOBALS['theme']['head'] ?? [],
                    'footer' => $GLOBALS['theme']['footer'] ?? [],
                ],
                'menus' => $menus,
            ]
        ];
        $twig_views = [];
        $theme_keys = Caching::init()->get("system.theme.keys") ?? [];

        if ($theme_keys) {
            foreach ($theme_keys as $theme) {
                if (Caching::init()->has($theme)) {
                    $template = Caching::init()->get($theme);
                    if ($template instanceof TwigResolver) {
                        $twig_views[$theme] = $template->__toString();
                    }
                }
            }
        }

        $loader = new ArrayLoader($twig_views);
        //$twig_options = Yaml::parseFile($this->schema_dir.DIRECTORY_SEPARATOR.'manifest.yml')['twig_setting'] ?? [];
        $twig_options = [
            'debug' => true,
            'cache' => false,
            'strict_variables' => FALSE,
            'charset' => 'UTF-8',
        ];

        $this->twig_functions[] = new TwigFunction('dump', function ($var): void {
            dump($var);
        });
        $this->twig_functions[] = new TwigFunction('dd', function ($asset): void {
            dd($asset);
        });

        $module_handler = ModuleHandler::factory();
        $modules = $module_handler->getModules();
        foreach ($modules as $key=>$module) {
            $install_module = $module['path']. DIRECTORY_SEPARATOR . $key. '.install.php';
            if (file_exists($install_module)) {
                require_once $install_module;
                $twig_function = $key. '_twig_function_install';
                $twig_filter = $key. '_twig_filter_install';
                if (function_exists($twig_function)) {
                    $this->twig_functions = array_merge($this->twig_functions, $twig_function());
                }
                if (function_exists($twig_filter)) {
                    $this->twig_filters = array_merge($this->twig_filters, $twig_filter());
                }
            }
        }

        $this->twig = new Environment($loader, [
            ...$twig_options,
        ]);

        if (!empty($this->twig_functions)) {
            foreach ($this->twig_functions as $function) {
                if ($function instanceof TwigFunction) {
                    $this->twig->addFunction($function);
                }
            }
        }

        if (!empty($this->twig_filters)) {
            foreach ($this->twig_filters as $filter) {
                if ($filter instanceof TwigFilter) {
                    $this->twig->addFilter($filter);
                }
            }
        }

        // add extra extension for twig

        foreach ($modules as $key=>$module) {
            $install_module = $module['path']. DIRECTORY_SEPARATOR . $key. '.install.php';
            if (file_exists($install_module)) {
                require_once $install_module;
                $twig_extension = $key. '_twig_extension_install';
                if (function_exists($twig_extension)) {
                    $this->extra_extensions = array_merge($this->extra_extensions, $twig_extension());
                }
            }
        }

        if (!empty($this->extra_extensions)) {
            foreach ($this->extra_extensions as $extension) {
                $this->twig->addExtension($extension);
            }
        }
    }
    public function getTwigFunctions(): array
    {
        return $this->twig_functions;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

}