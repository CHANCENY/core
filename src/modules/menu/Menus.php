<?php

namespace Simp\Core\modules\menu;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\lib\routes\Route;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\roles\RoleManager;

class Menus
{
    protected array $menus = [];
    protected RoleManager $roleManager;

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $this->roleManager = CurrentUser::currentUser()->getUser()->roleManager();
        $main_menu = [];

        $current_user = CurrentUser::currentUser();

        $main_menu['system.home'] = 'home.page.route';

        if ($current_user->isIsAdmin()) {


            $main_menu['system.content'] = 'system.structure.content';
            $main_menu['system.structure'] = 'system.structure';
            $main_menu['system.extend'] = 'system.extends.manage';
            $main_menu['system.config'] = 'system.configuration';
            $main_menu['system.people'] = 'system.people';
            $main_menu['system.report'] = 'system.reports.errors';
           // $main_menu['system.logout'] = 'user.account.logout.route';

        }

        elseif ($current_user->isIsContentCreator() || $current_user->isIsManager()) {

            $main_menu['system.content'] = 'system.structure.content';
            $main_menu['system.people'] = 'system.people';
           // $main_menu['system.logout'] = 'user.account.logout.route';
        }

        if (!$current_user->isIsLogin()) {
            $main_menu['system.login'] = 'user.account.login.form.route';
            $main_menu['system.register'] = 'user.account.form.page.route';
        }

        foreach ($main_menu as $key => $value) {
            $this->menus[$key] = new Menu($value);
        }

        $module_handler = ModuleHandler::factory();
        $modules = $module_handler->getModules();
        foreach ($modules as $key=>$module) {
            if ($module_handler->isModuleEnabled($key)) {
                $module_install = $module['path'] . DIRECTORY_SEPARATOR . $key. '.install.php';
                if (file_exists($module_install)) {
                    require_once $module_install;
                    $menu_install = $key . '_menu_install';
                    if (function_exists($menu_install)) {
                        $menu_install($this->menus);
                    }
                }
            }
        }

        if ($current_user->isIsLogin()) {

            // add a child menu to people of view
            $account_route = Route::fromRouteName('system.account.view');
            $route = $account_route->toArray();
            $route['path'] = '/user/'. CurrentUser::currentUser()->getUser()->getUid();
            $account = new Menu(['route_id' => 'system.account.view', 'route_data' => $route]);

            // add a child menu to account menu
            $edit_account = Route::fromRouteName('system.account.edit');
            $route = $edit_account->toArray();
            $route['path'] = '/user/'. CurrentUser::currentUser()->getUser()->getUid() .'/edit';
            $account->addChild(new Menu(['route_id' => 'system.account.edit', 'route_data' => $route]));
            $this->menus['system.people']->addChild($account);

            $profile_edit = Route::fromRouteName('system.account.profile.edit');
            $route = $profile_edit->toArray();
            $route['path'] = '/user/'. CurrentUser::currentUser()->getUser()->getUid() .'/profile/edit';
            $account->addChild(new Menu(['route_id' => 'system.account.profile.edit', 'route_data' => $route]));
            $account->addChild(new Menu('user.account.password.forgot'));
            $account->addChild(new Menu('user.account.logout.route'));
        }

        // now let's remove menu parent based on current user roles
        foreach ($this->menus as $key => $menu) {
            $access_roles = $menu->getMenu()?->access ?? [];
            $flag = false;
            foreach ($access_roles as $access_role) {
                if ($this->roleManager->isRoleExist($access_role)) {
                    $flag = true;
                    break;
                }
            }

            if (!$flag) {
                unset($this->menus[$key]);
            }
        }
    }

    public function getMenu(string $key): ?Menu
    {
        return $this->menus[$key] ?? null;

    }

    PUBLIC function getMenus(): array
    {
        return $this->menus;
    }

    public static function menus(): self {
        return new self();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $menu_links = [];
        foreach ($this->menus as $key => $menu) {

            try{
                /**@var Menu $menu */
                $menu_links[] = View::view('default.view.menu_item', ['menu'=>$menu]);
            }catch (\Throwable $e){
            }

        }
        return implode('', $menu_links);
    }
}