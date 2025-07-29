<?php

namespace Simp\Core\lib\middlewares;

use Simp\Core\modules\auth\normal_auth\AuthUser;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Router\middleware\access\Access;
use Simp\Router\middleware\interface\Middleware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class RouteAccessMiddleware implements Middleware 
{
    public function __invoke(Request $request, Access $access_interface, $next)
    {
        $current_user = CurrentUser::currentUser();
        $access_roles = $access_interface->options['options']['access'] ?? [];
        $redirect = new RedirectResponse('/error/page/access-denied');

        // Check if access roles are set if not redirect to access denied.
        if(empty($access_roles)) {
            $access_interface->redirect = $redirect;
            $access_interface->access_granted = false;
            return $next($request, $access_interface);
        }

        // Check if current user not exist if yes check if access roles have anonymous.
        if(is_null($current_user) && in_array('anonymous',$access_roles)) {
            $access_interface->redirect = $redirect;
            $access_interface->access_granted = true;
            return $next($request, $access_interface);
        }
    
        $roles = $current_user?->getUser()?->getRoles() ?? [];
        $roles = array_map(function($role){ return $role->getRoleName(); }, $roles);

        if($current_user instanceof AuthUser && array_intersect($access_roles, $roles)) {
            $access_interface->access_granted = true;
        }
        else {
            $access_interface->redirect = $redirect;
            $access_interface->access_granted = false;
        }
        return $next($request, $access_interface);
    }
}