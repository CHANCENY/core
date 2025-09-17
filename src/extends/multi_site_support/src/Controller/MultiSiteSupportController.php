<?php

namespace Simp\Core\extends\multi_site_support\src\Controller;

use DI\DependencyException;
use DI\NotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\components\request\Request;
use Simp\Core\extends\multi_site_support\src\Plugin\MultiSiteSupport;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\theme\ThemeManager;
use Simp\Core\modules\user\roles\RolesRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MultiSiteSupportController
{
    /**
     * @param mixed ...$args
     * @return Response
     * @throws DependencyException
     * @throws LoaderError
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function index(...$args): Response {

        ModuleHandler::factory()->attachLibrary('multi_site_support', 'multi_site_support.assets');

        $sites = Service::get(MultiSiteSupport::class)->getSites();
        $new_sites = [];
        foreach ($sites as $key => &$value) {
            $value['id'] = $key;
        }
        $sites = array_values($sites);
        $themes = ThemeManager::manager()->getThemes();

        foreach ($themes as $key => &$value) {
            $value['id'] = $key;
        }
        $themes = array_values($themes);

        /**@var RolesRepository $roles**/
        $roles = Service::get('system.roles');

        $primary_roles = $roles->getPrimaryRoles();
        $secondary_roles = $roles->getSecondaryRoles();

        return new Response(View::view('default.view.multi_site_support.dashboard',
            [
                'sites'=>$sites,
                'themes'=>$themes,
                'primary_roles'=>$primary_roles,
                'secondary_roles'=>$secondary_roles,
            ]
        ));
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function save(...$args): JsonResponse
    {
        extract($args);

        /**@var Request $request**/
        $content = json_decode($request->getContent(), true);

        $multi_site = new MultiSiteSupport();

        if (!empty($content)) {
            foreach ($content as $key => $value) {
                $multi_site->addSite($value);
            }
        }

        return new JsonResponse(['success' => true, 'message' => 'Site saved successfully', 'content' => $content]);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function delete(...$args): JsonResponse
    {
        extract($args);
        $id = $request->get('id');
        $multi_site = new MultiSiteSupport();
        $multi_site->removeSite($id);
        return new JsonResponse(['success' => true, 'message' => 'Site deleted successfully']);
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
    public function blocked(...$args): Response
    {

        ModuleHandler::factory()->attachLibrary('multi_site_support', 'multi_site_support.blocked');
        return new Response(View::view('default.view.multi_site_support.blocked'),503);
    }
}