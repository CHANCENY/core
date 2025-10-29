<?php

namespace Simp\Core\modules\pages;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\logger\ErrorLogger;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\user\current_user\CurrentUser;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\TwigFunction;

class TemplateLoader
{

    protected Environment $twig;

    protected string $templateRendererString;
    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     */
    public function __construct(string $templatePath, array $options = [])
    {
        // twig settings
       
        $request = Request::createFromGlobals();
        
        $current_user = CurrentUser::currentUser();

        $assets_root = $request->getSchemeAndHttpHost() . '/static';

        $list = explode('/', $templatePath);
        $static_index = array_search('static', $list);
        if ($static_index !== false) {
            $assets_root .= "/". $list[$static_index + 1] . "/";
        }

        // defaults options for twig
        $defaults = [
            'request' => $request,
            'host' => $request->getHttpHost(),
            'path' => $request->getPathInfo(),
            'scheme' => $request->getScheme(),
            'base_url' => $request->getBaseUrl(),
            'is_secure' => $request->isSecure(),
            'is_ajax' => $request->isXmlHttpRequest(),
            'domain' => $request->getSchemeAndHttpHost(),
            'current_url' => $request->getUri(),
            'current_url_path' => $request->getPathInfo(),
            'current_url_query' => $request->getQueryString(),
            'requesting_uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('referer'),
            'is_login' => $current_user->isIsLogin(),
            'is_admin' => $current_user->isIsAdmin(),
            'is_authenticated' => $current_user->isIsAuthenticated(),
            'is_content_creator' => $current_user->isIsContentCreator(),
            'is_manager' => $current_user->isIsManager(),
            'user' => $current_user->getUser(),
            'user_id' => $current_user->getUser()->getUid(),
            'user_name' => $current_user->getUser()->getName(),
            'user_email' => $current_user->getUser()->getMail(),
            'user_roles' => $current_user->getUser()->getRoles(),
            'login_on' => $current_user->getUser()->getLogin(),
            'page_title' => $options['page_title'] ?? Route::getCurrentRoute()->route_title ?? 'Welcome Page',
            'page_description' => $options['page_description'] ?? '',
            'page_keywords' => $options['page_keywords'] ?? '',
            'page_author' => $options['page_author'] ?? '',
            'page_copyright' => $options['page_copyright'] ?? '',
            'page_robots' => $options['page_robots'] ?? '',
            'page_canonical' => $options['page_canonical'] ?? $request->getUri(),
            'asset_root' => $assets_root,

            ...$options,
        ];

        $system = Service::get('system.directory');

        $templateDirectory = $system->webroot_dir . DIRECTORY_SEPARATOR . 'static';

        $loader = new \Twig\Loader\FilesystemLoader($templateDirectory);
        $twig = new \Twig\Environment($loader, [
            'cache' => false,
            'debug' => false,
        ]);
        $twig->addGlobal('options', $defaults);
        $twig->addGlobal('route', Route::getCurrentRoute());
        $twig->addGlobal('current_user', $current_user);
        $twig->addGlobal('request', $request);

        $twig->addExtension(new \Twig\Extension\DebugExtension());
        $twig->addExtension(new \Twig\Extension\StringLoaderExtension());

       $twig->addFunction(new TwigFunction('url', [$this, 'url']));

        $this->twig = $twig;
        try {

            // remove $templateDirectory from $templatePath
            $templatePath = str_replace($templateDirectory, '', $templatePath);
            $this->templateRendererString = $twig->render($templatePath, $defaults);
        } catch (\Throwable $e) {
            ErrorLogger::logger()->logError($e);
        }

    }

    public function __toString(): string
    {
        return $this->templateRendererString;
    }

    /**
     * Load and render a template
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function render(string $templatePath, array $options = []): string
    {
        $loader = new self($templatePath, $options);
        return (string) $loader;
    }

    public function url(string $file_path, array $options = [])
    {
        $pageLoader = new PageLoader();

        $routes = $pageLoader->getRoutes();

        $extension = pathinfo($file_path, PATHINFO_EXTENSION);

        if ($extension === 'html' || $extension === 'htm') {
            $file_path = preg_replace('/\.(html|htm)$/i', '.twig', $file_path);
        }

       foreach ($routes as $route) {

           $file = $route['options']['file'] ?? '';
           $uri = $route['path'] ?? null;

           $list = explode('/', $file);
           $file_name = end($list);

           if (strtolower($file_name) === strtolower($file_path) || $uri === $file_path) {
               return $uri;
           }

       }

       return '#';
    }
}