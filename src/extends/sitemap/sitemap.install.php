<?php

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\sitemap\src\Controller\SiteMapController;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\menu\Menu;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\structures\taxonomy\Term;

function sitemap_route_install(): array
{
    return [
        'sitemap.xml' => [
            'title' => 'Sitemap',
            'description' => 'Sitemap',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => SiteMapController::class,
                'method' => 'index',
            ],
            'access' => [
                'administrator',
                'authenticated',
                'anonymous',
                'content_creator',
                'manager'
            ],
            'options' => [],
            'path' => '/sitemap.xml',
        ],
        'sitemap.xml.dashboard' => [
            'title' => 'Sitemap Settings',
            'description' => 'Sitemap',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => SitemapController::class,
                'method' => 'dashboard',
            ],
            'access' => [
                'administrator',
            ],
            'options' => [
                'classes' => ['fa-solid', 'fa-sitemap']
            ],
            'path' => '/sitemap/dashboard',
        ]
    ];
}

function sitemap_template_install(): array
{
    $module = ModuleHandler::factory()->getModule('sitemap');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

function sitemap_sitemap_generator_install(): array
{
    return [
      'sitemap.node.path' => fn(int $page = 0): array =>
          // Key paths need urls
          [
          'title' => 'Contents URLs',
          'paths' => getContentURLs($page),
      ],
      'sitemap.terms.path' => fn(int $page = 0): array => [
          'title' => 'Terms URLs',
          'paths' => getTerms()
      ],
      'sitemap.others.path' => fn(int $page = 0): array => [
          'title' => 'Others URLs',
          'paths' => getOthers()
      ]
    ];
}

function sitemap_menu_install(array &$menus): void
{
    $menus['system.config']->addChild(new Menu('sitemap.xml.dashboard'));
}

function getContentURLs(int $page): array
{
    // Load configuration of which content type nodes to have its urls
    $settings = \Simp\Core\modules\config\ConfigManager::config()->getConfigFile('sitemap');

    $content_types = $settings->get('content_type');

    if (empty($content_types)) {
        return [];
    }

    $node_storage = Node::nodeStorage('');
    $params = array_map(fn(int|string $item): string => ":placeholder_".$item,array_keys($content_types));

    $values = array_combine($params, array_values($content_types));


    $node_storage->addWhere("bundle IN (".implode(',',$params).")",$values);
    $node_storage->execute();

    $paths = [];

    /**@var Node $node**/
    foreach ($node_storage as $node) {

        $paths[] = [
            'title' => $node->getTitle(),
            'modified' => date('c',strtotime((string) $node->getUpdated())),
            'url' => Route::url('system.structure.content.node',['nid'=>$node->getNid()])
        ];

    }

    return $paths;

}

function getTerms(): array
{
    $settings = \Simp\Core\modules\config\ConfigManager::config()->getConfigFile('sitemap');

    $terms = $settings->get('terms');

    if (empty($terms)) {
        return [];
    }

    $terms_storage = Term::termStorage();

    $params = array_map(fn(int|string $item): string => ":placeholder_".$item,array_keys($terms));

    $values = array_combine($params, array_values($terms));

    $terms_storage->addWhere("vid IN (".implode(',',$params).")",$values);
    $terms_storage->execute();

    $paths = [];
    foreach ($terms_storage as $term) {
        $paths[] = [
            'title' => $term['label'],
            'modified' => date('c',strtotime((string) $term['created_at'])),
            'url' => Route::url('system.vocabulary.term.view',['name'=>$term['name']]),
            'priority' => 0.64,
        ];
    }

    return $paths;
}

/**
 * @throws PhpfastcacheCoreException
 * @throws PhpfastcacheLogicException
 * @throws PhpfastcacheDriverException
 * @throws PhpfastcacheInvalidArgumentException
 */
function getOthers(): array
{

    $settings = \Simp\Core\modules\config\ConfigManager::config()->getConfigFile('sitemap');

    $ignore = $settings->get('ignore_routes',[]);

    $routes_all = Route::getRoutes();

    $paths = [];

    // site date formatted for lastmod
    $now = (new \DateTime())->format('c');

    $ignore = [
        ...$ignore,
        "database.form.route",
        "home.page.route",
        "system.error.page.denied",
        "user.account.login.form.route",
        "user.account.google.redirect.route",
        "user.account.github.redirect.route",
        "user.account.form.page.route",
        "user.account.password.forgot",
        "system.assets.loader",
        "system.files.upload.ajax",
        "system.files.upload.delete.ajax",
        "page_builder.action.search",
        "sitemap.xml"
    ];

    foreach ($routes_all as $route) {

        if ($route instanceof Route && (!in_array($route->route_id, $ignore) && in_array('anonymous', $route->access) && in_array('GET',$route->method) && !str_contains($route->route_path, '['))) {

            $paths[] = [
                'title' => $route->route_title,
                'modified' => $now,
                'url' => $route->route_path,
                'priority' => 0.64
            ];

        }

    }

    return $paths;

}