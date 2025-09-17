<?php

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\multi_site_support\src\Controller\MultiSiteSupportController;
use Simp\Core\modules\menu\Menu;

/**
 * Hooks file
 */

function multi_site_support_menu_install(array &$menus): void
{
    if (!empty($menus['system.config'])) {
        $menus['system.config']->addChild(new Menu('multi_site_support.dashboard'));
    }

}

/**
 * @return string[]
 */
function multi_site_support_template_install(): array
{
    $module = ModuleHandler::factory()->getModule('multi_site_support');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

/**
 * Registers and installs routes for multi-site support.
 *
 * @return array Returns an array of installed routes.
 */
function multi_site_support_route_install(): array
{

    return [
        'multi_site_support.dashboard' => [
            'title' => 'Multi Site Support',
            'path' => '/admin/multi-site-support/dashboard',
            'method' => ['GET', 'POST'],
            'access' => ['administrator'],
            'controller' => [
                'class' => MultiSiteSupportController::class,
                'method' => 'index'
            ],
            'options' => [
                'classes' => ['fa-solid fa-sitemap']
            ]
        ],
        'multi_site_support.action.save' => [
            'title' => 'Multi Site Support Save',
            'path' => '/admin/multi-site-support/save',
            'method' => ['POST'],
            'access' => ['administrator'],
            'controller' => [
                'class' => MultiSiteSupportController::class,
                'method' => 'save'
            ],
            'options' => []
        ],
        'multi_site_support.action.delete' => [
            'title' => 'Multi Site Support Delete',
            'path' => '/admin/multi-site-support/[id:string]/delete',
            'method' => ['GET'],
            'access' => ['administrator'],
            'controller' => [
                'class' => MultiSiteSupportController::class,
                'method' => 'delete'
            ],
            'options' => []
        ],
        'multi_site_support.blocked' => [
            'title' => 'Site Support Blocked',
            'path' => '/domain/blocked',
            'method' => ['GET'],
            'access' => ['anonymous'],
            'controller' => [
                'class' => MultiSiteSupportController::class,
                'method' => 'blocked'
            ],
            'options' => []
        ]
    ];
}


function multi_site_support_library_install(string $library_name): array
{
    return [
        'multi_site_support.assets' => [
            'head' => [
                '/core/modules/multi_site_support/assets/multi-main.css'
            ],
            'footer' => [
                '/core/modules/multi_site_support/assets/multi-main.js'
            ]
        ],
        'multi_site_support.blocked' => [
            'head' => [
                '/core/modules/multi_site_support/assets/multi-blocked.css'
            ],
            'footer' => [
            ]
        ]
    ][$library_name] ?? [];
}

function multi_site_support_middleware_install(): array
{
    return [
        \Simp\Core\extends\multi_site_support\src\Middleware\MultiSiteSupportMiddleware::class,
    ];

}