<?php

namespace Simp\Core\lib\middlewares;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\entity\User;
use Simp\Router\middleware\access\Access;
use Simp\Router\middleware\interface\Middleware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class AccountOwnerShipMiddleware implements Middleware
{
    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     */
    public function __invoke(Request $request, Access $access_interface, $next)
    {
        $user = CurrentUser::currentUser()?->getUser() ?? null;
        $uid = $request->get('uid', $request->request->get('uid'));
        $redirect = new RedirectResponse('/error/page/access-denied');

        // Just return if $redirect or $response and access_granted is false is set in $access_interface 
        if($access_interface->access_granted === false && (isset($access_interface->response) || isset($access_interface->redirect))) {
            return $next($request, $access_interface);
        }

        // Check 
        if($user instanceof User && !is_null($uid)) {

            $user_loaded = User::load($uid);
            // Lets check if current user has ability to act on $uid given.
            $route = $access_interface->options['options']['route'] ?? null;
            $route_id = null;
            if ($route instanceof Route) {
                $route_id = $route->route_id;
            }
            elseif (is_array($route)) {
                $route_id = $route['route_id'] ?? null;
            }

            // Lets check for all routes that deal with accounts.
            $account_id = [
                'system.account.view', 
                'system.account.profile.edit', 
                'system.account.edit',
                'system.account.delete'
            ];
            
            if (in_array($route_id, $account_id)) {

                if (CurrentUser::currentUser()->isIsAdmin() || CurrentUser::currentUser()->isIsManager()) {
                    $access_interface->access_granted = true;
                    return $next($request, $access_interface);
                }

                elseif (CurrentUser::currentUser()->isIsContentCreator()) {

                    if ($route_id !== 'system.account.view') {
                        $access_interface->access_granted = false;
                        $access_interface->redirect = $redirect;
                    }
                    return $next($request, $access_interface);
                }

                elseif (CurrentUser::currentUser()->isIsAuthenticated()) {

                    if (CurrentUser::currentUser()->getUser()->getUid() == $user_loaded->getUid()) {
                        $access_interface->access_granted = true;
                        return $next($request, $access_interface);
                    }
                    $access_interface->access_granted = false;
                    $access_interface->redirect = $redirect;
                    return $next($request, $access_interface);
                }

            }
        }
        return $next($request, $access_interface);
    }
}