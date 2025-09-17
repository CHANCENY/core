<?php

namespace Simp\Core\extends\multi_site_support\src\Middleware;

use DI\DependencyException;
use DI\NotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\extends\multi_site_support\src\Plugin\MultiSiteSupport;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Router\middleware\access\Access;
use Simp\Router\middleware\interface\Middleware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class MultiSiteSupportMiddleware implements Middleware
{

    /**
     * @throws PhpfastcacheCoreException
     * @throws NotFoundException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws DependencyException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __invoke(Request $request, Access $access_interface, $next)
    {
        $route = Route::fromRouteName('multi_site_support.blocked');
        if ($route->route_path === $request->getRequestUri()) {
            $access_interface->access_granted = true;
            return $next($request, $access_interface);
        }

        $redirect = new RedirectResponse('/error/page/access-denied');
        $current_user = CurrentUser::currentUser();

        if (!MultiSiteSupport::isMultiSiteSupportEnabled()) {
            return $next($request, $access_interface);
        }

        $domain = $request->getHttpHost();
        $site = Service::get(MultiSiteSupport::class)->getSiteByDomain($domain);

        // If we have a site under this domain.
        if (!empty($site)) {

            $primary_roles = [$site['primaryRole'], 'administrator'];
            $secondary_roles = $site['additionalRoles'];

            $all_roles = array_merge($primary_roles, $secondary_roles);
            $all_roles = array_unique($all_roles);

            $user_roles = array_map(function ($role){ return $role->getName(); },$current_user->getUser()->roleManager()->getRoles());

            // Check if a user has any of the roles
            if (count(array_intersect($all_roles, $user_roles)) > 0) {
                $access_interface->access_granted = true;
            }
            else {

                $access_interface->access_granted = false;
                $access_interface->redirect = $redirect;

            }
            return $next($request, $access_interface);

        }

        $redirect = new RedirectResponse(Route::url('multi_site_support.blocked'));
        $access_interface->access_granted = false;
        $access_interface->redirect = $redirect;
        return $next($request, $access_interface);
    }
}