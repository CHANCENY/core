<?php

namespace Simp\Core\extends\sitemap\src\Controller;

use DI\DependencyException;
use DI\NotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\components\request\Request;
use Simp\Core\extends\sitemap\src\Form\SiteMapForm;
use Simp\Core\extends\sitemap\src\SiteMapGenerator;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\services\Service;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SiteMapController
{
    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function index(...$args): Response
   {
       $modules = ModuleHandler::factory()->getModules();
       $sitemap_handlers = [];

       foreach($modules as $name=>$module) {
           $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
           if (file_exists($module_installer) && $module['enabled'] === true) {
               require_once $module_installer;
               $generator_install = $name . '_sitemap_generator_install';
               if (\function_exists($generator_install)) {
                   $sitemap_handlers = \array_merge($sitemap_handlers, $generator_install());
               }
           }
       }

       $entries = [];
       foreach($sitemap_handlers as $name=>$handler) {
           $entries[$name] = $handler(Service::get('request')->get('page', 0));
       }

       /** @var Request $request */
       $request = Service::get('request'); // or inject RequestStack in Symfony services

       $userAgent = strtolower($request->headers->get('User-Agent', ''));

       $isBrowser = false;
       $isBot = false;

       if ($userAgent) {
           // Common bots
           $bots = ['googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandex'];

           foreach ($bots as $bot) {
               if (strpos($userAgent, $bot) !== false) {
                   $isBot = true;
                   break;
               }
           }

           // Rough browser check (you can refine this)
           if (preg_match('/mozilla|chrome|safari|firefox|edge/i', $userAgent)) {
               $isBrowser = true;
           }
       }

       if ($isBrowser) {
           return new Response(View::view('default.view.sitemap.view', ['sitemap'=>$entries]));
       }

       // Generate the sitemap xml here
       $grouped_paths = array();
       foreach ($entries as $name=>$entry) {
           $grouped_paths = array_merge($grouped_paths, $entry['paths']);
       }

       // Give a proper limit per sitemap max url in one page
       // if multiple pages are need give proper XML file data
       $limit = 10;
       $page = $request->get('page', 0);

       $sitemap_generator = new SiteMapGenerator($grouped_paths, $limit, $page);
       return $sitemap_generator->generate($request);
   }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function dashboard(...$args): Response
   {
       $form = new FormBuilder(new SiteMapForm());
       $form->getFormBase()->setFormMethod('POST');

       return new Response(View::view('default.view.sitemap.sitemap_dashboard',['_form'=>$form]));
   }
}