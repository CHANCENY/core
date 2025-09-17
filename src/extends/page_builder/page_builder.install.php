<?php


use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\page_builder\src\Controller\PageBuilderController;
use Simp\Core\extends\page_builder\src\Field\PageBuilderFieldBuilder;
use Simp\Core\extends\page_builder\src\Plugin\Page;
use Simp\Core\extends\page_builder\src\Plugin\PdfLink;
use Simp\Core\modules\database\Database;
use Twig\TwigFunction;

function page_builder_database_install(): bool
{
    $connection = Database::database()->con();

    // add your database tables install code here
    $queries = "CREATE TABLE IF NOT EXISTS page_builder_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(400) NOT NULL,
    title VARCHAR(400),
    version INT(11) NOT NULL,
    css LONGTEXT,
    content LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status INT(11) NOT NULL DEFAULT 0
)";

    $connection->exec($queries);
    // run query
    return $connection->prepare($queries)->execute();
}

function page_builder_route_install(): array
{
    return [
        'page_builder.create' => [
            'title' => 'Page Create',
            'path' => '/admin/page-builder/create',
            'method' => [
                'GET',
                'POST'
            ],
            'controller' => [
                'class' => PageBuilderController::class,
                'method' => 'create'
            ],
            'access' => [
                'administrator',
            ],
            'options' => [
                'classes' => ['fa','fa-plus']
            ]
        ],
        'page_builder.list' => [
            'title' => 'Page Building',
            'path' => '/admin/page-builder/list',
            'method' => [
                'GET'
            ],
            'controller' => [
                'class' => PageBuilderController::class,
                'method' => 'dashboard'
            ],
            'access' => [
                'administrator',
            ],
            'options' => [
                'classes' => ['fa fa-list']
            ]
        ],
        'page_builder.action.save' => [
            'title' => 'Page Builder Save',
            'path' => '/admin/page-builder/templates/save',
            'method' => [
                'POST'
            ],
            'controller' => [
                'class' => PageBuilderController::class,
                'method' => 'save'
            ],
            'access' => [
                'administrator',
            ],
            'options' => [
                'classes' => ['fa fa-save']
            ]
        ],
        'page_builder.action.search' => [
            'title' => 'Page Contents Search',
            'path' => '/page-builder/search',
            'method' => [
                'POST',
                'GET'
            ],
            'controller' => [
                'class' => PageBuilderController::class,
                'method' => 'search'
            ],
            'access' => [
                'anonymous',
                'authenticated',
                'content_creator',
                'manager',
                'administrator'
            ],
            'options' => [
                'classes' => ['fa fa-search']
            ]
        ],
        'page_builder.action.embeddable' => [
            'title' => 'Page',
            'path' => '/page-builder/[pid:int]/embeddable',
            'method' => [
                'POST',
                'GET'
            ],
            'controller' => [
                'class' => PageBuilderController::class,
                'method' => 'embeddable'
            ],
            'access' => [
                'anonymous',
                'authenticated',
                'content_creator',
                'manager',
                'administrator'
            ],
            'options' => [
                'classes' => ['fa fa-search']
            ]
        ],
        'page_builder.action.link' => [
            'title' => 'Page',
            'path' => '/[name:string]/[pid:int]/content',
            'method' => [
                'POST',
                'GET'
            ],
            'controller' => [
                'class' => PageBuilderController::class,
                'method' => 'link'
            ],
            'access' => [
                'anonymous',
                'authenticated',
                'content_creator',
                'manager',
                'administrator'
            ],
            'options' => [
                'classes' => ['fa fa-search']
            ]
        ]
    ];
}

function page_builder_template_install(): array {
    $module = ModuleHandler::factory()->getModule('page_builder');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

function page_builder_menu_install(array &$menus): void
{
    $page_builder_menu = new \Simp\Core\modules\menu\Menu('page_builder.list');
    $page_builder_menu->addChild(new \Simp\Core\modules\menu\Menu('page_builder.create'));
    $menus['system.config']->addChild($page_builder_menu);
}

function page_builder_field_install(): array
{
    return [
        'page_builder' => PageBuilderFieldBuilder::class,
    ];
}

function page_builder_library_install(string $library_name): array
{
    $library = [
        'page.builder.library' => [
            'head' => [
                '/core/modules/page_builder/assets/styles.css'
            ],
            'footer' => [
                '/core/modules/page_builder/assets/pages-auto-suggest.js',
            ]
        ]
    ];
    return $library[$library_name] ?? [];
}

function page_builder_twig_function_install(): array
{
    return [
        new TwigFunction('load_page', fn(int $pid): \Simp\Core\extends\page_builder\src\Plugin\Page => Page::load($pid)),
        new TwigFunction('r_strip', function (string $string): string {
            // Replace anything that is not a letter, number, or space with a space
            $text = preg_replace('/[^a-zA-Z0-9\s]/', '-', $string);

            // Replace multiple spaces with a single space
            $text = preg_replace('/\s+/', '-', $text);

            // Trim leading/trailing spaces
            return trim($text);
        }),
        new TwigFunction('pdf_link',fn(Page $page): string => PdfLink::factory($page)->getDownloadLink()),
        new TwigFunction('pdf_file',fn(Page $page): ?\Simp\Core\modules\files\entity\File => PdfLink::factory($page)->getFile())
    ];
}
