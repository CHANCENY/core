<?php

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\system\src\Controller\System;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\menu\Menu;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\views\ViewsManager;
use Simp\Core\modules\user\current_user\CurrentUser;

/**
 *  System hooks list
 */

function system_route_install(): array
{
    return [
        'system.module.rebuild' => [
            'title' => 'Rebuild Core',
            'path' => '/admin/system/module/rebuild',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => System::class,
                'method' => 'system_rebuild'
            ],
            'access' => ['administrator'],
            'options' => [
                'classes' => ['fa','fa-gear']
            ]
        ],
        'system.module.system' => [
            'title' => 'System',
            'path' => '/admin/system',
            'method' => ['GET'],
            'controller' => [
                'class' => System::class,
                'method' => 'system'
            ],
            'access' => ['administrator'],
            'options' => [
                'classes' => ['fa','fa-gear']
            ]
        ],
        'system.rebuild.cache' => [
            'title' => 'Rebuild Cache',
            'path' => '/admin/system/rebuild/cache',
            'method' => ['GET'],
            'controller' => [
                'class' => System::class,
                'method' => 'rebuild_cache'
            ],
            'access' => ['administrator'],
            'options' => [
                'classes' => ['fa','fa-gear']
            ]
        ],
        'system.module.clear.cache' => [
            'title' => 'Clear Cache',
            'path' => '/admin/system/clear/cache',
            'method' => ['GET'],
            'controller' => [
                'class' => System::class,
                'method' => 'clear_cache'
            ],
            'access' => ['administrator'],
            'options' => [
                'classes' => ['fa','fa-gear']
            ]
        ],
        'system.module.rebuild.all' => [
            'title' => 'Rebuild All',
            'path' => '/admin/system/rebuild/all',
            'method' => ['GET'],
            'controller' => [
                'class' => System::class,
                'method' => 'rebuild_all'
            ],
            'access' => ['administrator'],
            'options' => [
                'classes' => ['fa','fa-gear']
            ]
        ],
        'system.structure.content-type.rebuild' => [
            'title' => 'Rebuild Types Store',
            'path' => '/admin/system/structure/content-type/rebuild',
            'method' => ['GET'],
            'controller' => [
                'class' => System::class,
                'method' => 'content_types'
            ],
            'access' => ['administrator'],
            'options' => [
                'classes' => ['fa','fa-gear']
            ]
        ]
    ];
}

function system_twig_function_install(): array
{
    return [];
}

function system_database_install(): void { }

function system_cron_jobs_install(): array
{
    return [];
}

function system_template_install(): array
{
    $module = ModuleHandler::factory()->getModule('system');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

function system_cron_subscribers_install(): array
{
    return [];
}

/**
 * @throws PhpfastcacheCoreException
 * @throws PhpfastcacheInvalidArgumentException
 * @throws PhpfastcacheLogicException
 * @throws PhpfastcacheDriverException
 */
function system_menu_install(array &$menus): void
{
    if (CurrentUser::currentUser()->isIsAdmin()) {

        // append System menu
        $system_menu = new Menu('system.module.system');
        $system_menu->addChild(new Menu('system.module.rebuild'));
        $system_menu->addChild(new Menu('system.rebuild.cache'));
        $system_menu->addChild(new Menu('system.module.clear.cache'));
        $system_menu->addChild(new Menu('system.structure.content-type.rebuild'));
        $system_menu->addChild(new Menu('system.module.rebuild.all'));


        $menus = ['system.module' => $system_menu, ...$menus];

        //system.structure
        $content_types = new Menu('system.structure.content-type');

        $content_types_list = ContentDefinitionManager::contentDefinitionManager()
            ->getContentTypes();

        foreach ($content_types_list as $type=>$content_type) {

            $type_route = Route::fromRouteName('system.structure.content-type.manage')->toArray();
            $type_route['path'] = Route::url('system.structure.content-type.manage',['machine_name'=>$type]);
            $type_route['title'] = $content_type['name'] ?? $type;
            $content_types->addChild(new Menu(['route_id' => 'system.structure.content-type.manage', 'route_data' => $type_route]));

        }

        $views = new Menu('system.structure.views');

        $views_list = ViewsManager::viewsManager()->getViews();
        foreach ($views_list as $k_v=>$view) {

            $view_route = Route::fromRouteName('system.structure.views.display')->toArray();
            $view_route['path'] = Route::url('system.structure.views.display',['view_name'=>$k_v]);
            $view_route['title'] = $view['name'] ?? $k_v;
            $view_menu = new Menu(['route_id' => 'system.structure.views.display', 'route_data' => $view_route]);
            $views->addChild($view_menu);
        }

        $vocab = new Menu('system.vocabulary.list');

        $menus['system.structure']->addChild($content_types);
        $menus['system.structure']->addChild($views);
        $menus['system.structure']->addChild($vocab);

        // Content
        $menu_content = new Menu('system.structure.content.add');
       $menus['system.content']->addChild($menu_content);

        foreach ($content_types_list as $type=>$content_type) {

            $type_route = Route::fromRouteName('system.structure.content.form')->toArray();
            $type_route['path'] = Route::url('system.structure.content.form',['content_name'=>$type]);
            $type_route['title'] = $content_type['name'] ?? $type;
            $menu_content->addChild(new Menu(['route_id' => 'system.structure.content.form', 'route_data' => $type_route]));

        }

        $menus['system.content']->addChild(new Menu('system.files.add'));
        $menus['system.extend']->addChild(new Menu('system.extends.manage.add'));


        $menus['system.config']->addChild(new Menu('system.configuration.basic'));
        $menus['system.config']->addChild(new Menu('system.cron.manage'));
        $menus['system.config']->addChild(new Menu('system.configuration.smtp'));
        $menus['system.config']->addChild(new Menu('system.configuration.account'));
        $menus['system.config']->addChild(new Menu('system.search.settings'));
        $menus['system.config']->addChild(new Menu('system.configuration.logger'));
        $menus['system.config']->addChild(new Menu('system.reports.errors'));

    }
}

function system_field_install(): array
{
    return [];
}

function system_library_install(string $library_name): array
{
    return [];
}

function system_twig_filter_install(): array
{
    return [];
}

function system_twig_extension_install(): array
{
    return [];
}

function system_command_install(): array
{
    return [
        new \Simp\Core\extends\system\src\Plugin\SystemCommand(),
        new \Simp\Core\extends\system\src\Plugin\SystemCoreRebuildCommand(),
        new \Simp\Core\extends\system\src\Plugin\SystemRebuildTypeCommand(),
        new \Simp\Core\extends\system\src\Plugin\SystemRebuildAllCommand(),
    ];
}