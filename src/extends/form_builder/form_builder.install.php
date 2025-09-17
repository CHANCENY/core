<?php


use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\form_builder\src\Controller\FormBuilderController;
use Simp\Core\extends\form_builder\src\Controller\SubmissionHandler;
use Simp\Core\extends\form_builder\src\Field\FormBuilderField;
use Simp\Core\extends\form_builder\src\Field\FormBuilderFieldBuilder;
use Simp\Core\extends\form_builder\src\Plugin\FormConfigManager;
use Simp\Core\extends\form_builder\src\Plugin\FormSettings;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\menu\Menu;
use Simp\Core\modules\user\current_user\CurrentUser;


function form_builder_route_install(): array
{
    $form_builder_config = FormConfigManager::factory();
    $forms = $form_builder_config->getForms();
    $routes = [];
    foreach ($forms as $form) {
        $settings = FormSettings::factory($form['name']);

        $permissions = ['anonymous'];
        if (!in_array($settings->getRequireLogin(), ['', '0'], true)) {
            $permissions = ['authenticated', 'administrator', 'content_creator', 'manager'];
        }

        if ($settings->isFormActive()) {
            $routes['form_builder.form.'.$form['name']] = [
                'title' => $settings->getTitle(),
                'path' => $settings->getSlug(),
                'controller' => [
                    'class' => SubmissionHandler::class,
                    'method' => 'formSubmission'
                ],
                'method' => ['POST','GET'],
                'access' => $permissions,
                'options' => [
                    'form_name' => $form['name'],
                    'form' => $form
                ]
            ];
        }
    }

    return [
        ...$routes,
        'form_builder.dashboard' => [
            'title' => 'Form Builder',
            'path' => '/admin/form-builder',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'dashboard'
            ],
            'access' => ['administrator'],
            'options' => [
                'classes' => ['fa-solid','fa-building']
            ],
        ],
        'form_builder.saver' => [
            'title' => 'Form Builder',
            'path' => '/admin/form-builder/save',
            'method' => ['POST'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'save'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.list' => [
            'title' => 'Forms',
            'path' => '/admin/form-builder/list',
            'method' => ['GET'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'list'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.delete' => [
            'title' => 'Form Delete',
            'path' => '/admin/form-builder/[name:string]/delete',
            'method' => ['GET'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'delete'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.edit' => [
            'title' => 'Form Edit',
            'path' => '/admin/form-builder/[name:string]/edit',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'edit'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.form.settings' => [
            'title' => 'Form Settings',
            'path' => '/admin/form-builder/[name:string]/settings',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'form_settings'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.form.submission' => [
            'title' => 'Form Submission',
            'path' => '/admin/form-builder/[name:string]/submission',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'form_submission'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.form.submission.delete' => [
            'title' => 'Form Submission Delete',
            'path' => '/admin/form-builder/[name:string]/submission/[sid:int]/delete',
            'method' => ['GET'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'form_submission_delete'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.form.submission.edit' => [
            'title' => 'Form Submission Edit',
            'path' => '/admin/form-builder/[name:string]/submission/[sid:int]/edit',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'form_submission_edit'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.form.submission.view' => [
            'title' => 'Form Submission View',
            'path' => '/admin/form-builder/[name:string]/submission/[sid:int]/view',
            'method' => ['GET'],
            'controller' => [
                'class' => FormBuilderController::class,
                'method' => 'form_submission_view'
            ],
            'access' => ['administrator'],
            'options' => []
        ],
        'form_builder.form.submission.node' => [
            'title' => 'Form Submission Node',
            'path' => '/admin/form-builder/[name:string]/submission/node/[nid:int]/field/[field:string]',
            'method' => ['GET', 'POST'],
            'controller' => [
                'class' => SubmissionHandler::class,
                'method' => 'form_submission_node'
            ],
            'access' => [
                'administrator',
                'authenticated',
                'content_creator',
                'manager',
                'anonymous'
            ],
        ]
    ];
}

function form_builder_template_install(): array {
    $module = ModuleHandler::factory()->getModule('form_builder');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}


/**
 * @throws PhpfastcacheCoreException
 * @throws PhpfastcacheLogicException
 * @throws PhpfastcacheDriverException
 * @throws PhpfastcacheInvalidArgumentException
 */
function form_builder_menu_install(array &$menus): void
{
    $current_user = CurrentUser::currentUser();

    if ($current_user->isIsAdmin()){
        $menu_form_builder = new Menu('form_builder.dashboard');
        $list_menu = new Menu('form_builder.list');

        $forms = FormConfigManager::factory()->getForms();

        $route_settings = Route::fromRouteName('form_builder.form.settings');

        foreach ($forms as $form) {
            $route = [
                'route_id' => 'form_builder.form.settings',
                'route_data' => [
                    ...$route_settings->toArray()
                ]
            ];
            $route['route_data']['path'] = sprintf('/admin/form-builder/%s/settings', $form['name']);
            $route['route_data']['title'] = $form['title'];
            $ch_menu = new Menu($route);
            if (Route::fromRouteName('form_builder.form.'.$form['name']) instanceof \Simp\Core\lib\routes\Route) {
                $form_menu = new Menu('form_builder.form.'.$form['name']);
                $route_submission = Route::fromRouteName('form_builder.form.submission')->toArray();
                $route_submission['path'] = sprintf('/admin/form-builder/%s/submission', $form['name']);
                $route_submission['title'] = "Submissions";
                $ch_menu->addChild($form_menu);
                $ch_menu->addChild(new Menu(['route_id' => 'form_builder.form.submission', 'route_data' => $route_submission]));
            }

            $list_menu->addChild($ch_menu);
        }

        $menu_form_builder->addChild($list_menu);

        $menus['system.config']->addChild($menu_form_builder);
    }

   // dd($menus);

}


function form_builder_database_install(): void
{
    $query = "CREATE TABLE IF NOT EXISTS `form_settings` (id INT AUTO_INCREMENT NOT NULL PRIMARY KEY, form_name VARCHAR(200) NOT NULL, 
              title VARCHAR(200) NOT NULL, status VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL, notify VARCHAR(200) NULL, submit_limit INT DEFAULT 5,
              confirmation VARCHAR(500) NOT NULL,
              require_login VARCHAR(50) NULL,
               embedded LONGTEXT)";
    Database::database()
        ->con()->exec($query);

    $query = "CREATE TABLE IF NOT EXISTS `form_submissions` (
              `sid` INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
              `webform` VARCHAR(200) NOT NULL,
              `status` VARCHAR(200) NOT NULL,
              `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `ip` VARCHAR(45) NULL,
              `user_agent` TEXT NULL,
              `uid` INT NOT NULL)";
    Database::database()
        ->con()->exec($query);
    
    
}


function form_builder_field_install(): array
{
    return [
        'form_builder' => FormBuilderFieldBuilder::class,
    ];
}

function form_builder_library_install(string $library_name): array
{
    $library = [
        'form.builder.library' => [
            'head' => [
                '/core/modules/form_builder/assets/nod-submission-view.css'
            ],
            'footer' => [
                '/core/modules/form_builder/assets/node-submission-form.js',
            ]
        ],
        'form.builder.library.js' => [
            'head' => [
                '/core/modules/form_builder/assets/node-submission-form.js'
            ],
            'footer' => []
        ],
        'form.builder.library.css' => [
            'head' => [
                '/core/modules/form_builder/assets/nod-submission-view.css'
            ],
            'footer' => []
        ]
    ];
    return $library[$library_name] ?? [];
}
