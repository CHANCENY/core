<?php

namespace Simp\Core\lib\controllers;

use Exception;
use Simp\Core\components\site\SiteManager;
use Simp\Core\lib\routes\Route;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\theme\ThemeManager;
use Simp\Core\modules\tokens\TokenManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class HomeController
{
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     * @throws Exception
     */
    public function home_controller(...$args): Response
    {
        $site = SiteManager::factory();
        $home_controller = $site->get('front_page_url',null);
        $theme = ThemeManager::manager();
        $home_template = 'default.view.home';

        if (!empty($home_controller)) {
            $route = Route::fromRouteUrl($home_controller);
            if ($route !== null) {
                return Route::getControllerResponse($route);
            }
        }

        if (!CurrentUser::currentUser()?->isIsAdmin()) {
            if($theme->getCurrentTheme() !== null) {
                $home_template = $theme->getCurrentThemeHomeTemplate() ?? $home_template;
            }
        }

        return new Response(View::view($home_template),200);
    }
}