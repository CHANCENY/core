<?php

namespace Simp\Core\lib\middlewares;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Router\middleware\access\Access;
use Simp\Router\middleware\interface\Middleware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class NodeAccessMiddleware implements Middleware
{

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __invoke(Request $request, Access $access_interface, $next)
    {
        $user = CurrentUser::currentUser()?->getUser() ?? null;
        $nid = $request->get('nid', $request->request->get('nid'));
        $redirect = new RedirectResponse('/error/page/access-denied');

        // Just return if $redirect or $response and access_granted is false is set in $access_interface
        if($access_interface->access_granted === false && (isset($access_interface->response) || isset($access_interface->redirect))) {
            return $next($request, $access_interface);
        }
        $route = $access_interface->options['options']['route'] ?? null;
        $route_id = null;
        if ($route instanceof Route) {
            $route_id = $route->route_id;
        }
        elseif (is_array($route)) {
            $route_id = $route['route_id'];
        }


        $node_routes = [
            'system.structure.content.node',
            'system.structure.content.form.edit',
            'system.structure.content.form.delete'
        ];

        if (in_array($route_id, $node_routes) && !empty($nid)) {
            $node = Node::load($nid);
            if ($node instanceof Node) {
                $content_definitions = ContentDefinitionManager::contentDefinitionManager()->getContentType($node->getBundle());
                $permission = $content_definitions['permission'] ?? [];
                $user_roles = $user->getRoles();
                $roles = array_map(fn($role) => $role->getRoleName(), $user_roles);

                if (CurrentUser::currentUser()->isIsAdmin() || CurrentUser::currentUser()->isIsContentCreator() || CurrentUser::currentUser()->isIsManager()) {
                    $access_interface->access_granted = true;
                    return $next($request, $access_interface);
                }

                if ($route_id !== 'system.structure.content.node') {

                    if ($user->getUid() !== $node->getOwner()->getUid()) {
                        $access_interface->access_granted = false;
                        return $next($request, $access_interface);
                    }
                    $access_interface->access_granted = true;
                    return $next($request, $access_interface);
                }

                if (empty(array_intersect($permission, $roles))) {
                    $access_interface->access_granted = false;
                    return $next($request, $access_interface);
                }
            }
        }
        return $next($request, $access_interface);
    }
}