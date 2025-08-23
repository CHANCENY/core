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
        if ($settings->getRequireLogin()) {
            $permissions = ['authenticated', 'administrator', 'content_creator', 'manager'];
        }

        if ($settings->isFormActive()) {
            $routes['form_builder.form.'.$form['name']] = [
                'title' => $settings->getTitle(),
                'path' => $settings->getSlug(),
                'controller' => array(
                    'class' => SubmissionHandler::class,
                    'method' => 'formSubmission'
                ),
                'method' => array('POST','GET'),
                'access' => $permissions,
                'options' => [
                    'form_name' => $form['name'],
                    'form' => $form
                ]
            ];
        }
    }

    return array(
        ...$routes,
        'form_builder.dashboard' => array(
            'title' => 'Form Builder',
            'path' => '/admin/form-builder',
            'method' => array('GET', 'POST'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'dashboard'
            ),
            'access' => array('administrator'),
            'options' => array(
                'classes' => ['fa-solid','fa-building']
            ),
        ),
        'form_builder.saver' => array(
            'title' => 'Form Builder',
            'path' => '/admin/form-builder/save',
            'method' => array('POST'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'save'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.list' => array(
            'title' => 'Forms',
            'path' => '/admin/form-builder/list',
            'method' => array('GET'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'list'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.delete' => array(
            'title' => 'Form Delete',
            'path' => '/admin/form-builder/[name:string]/delete',
            'method' => array('GET'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'delete'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.edit' => array(
            'title' => 'Form Edit',
            'path' => '/admin/form-builder/[name:string]/edit',
            'method' => array('GET', 'POST'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'edit'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.form.settings' => array(
            'title' => 'Form Settings',
            'path' => '/admin/form-builder/[name:string]/settings',
            'method' => array('GET', 'POST'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'form_settings'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.form.submission' => array(
            'title' => 'Form Submission',
            'path' => '/admin/form-builder/[name:string]/submission',
            'method' => array('GET', 'POST'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'form_submission'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.form.submission.delete' => array(
            'title' => 'Form Submission Delete',
            'path' => '/admin/form-builder/[name:string]/submission/[sid:int]/delete',
            'method' => array('GET'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'form_submission_delete'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.form.submission.edit' => array(
            'title' => 'Form Submission Edit',
            'path' => '/admin/form-builder/[name:string]/submission/[sid:int]/edit',
            'method' => array('GET', 'POST'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'form_submission_edit'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.form.submission.view' => array(
            'title' => 'Form Submission View',
            'path' => '/admin/form-builder/[name:string]/submission/[sid:int]/view',
            'method' => array('GET'),
            'controller' => array(
                'class' => FormBuilderController::class,
                'method' => 'form_submission_view'
            ),
            'access' => array('administrator'),
            'options' => array()
        ),
        'form_builder.form.submission.node' => array(
            'title' => 'Form Submission Node',
            'path' => '/admin/form-builder/[name:string]/submission/node/[nid:int]/field/[field:string]',
            'method' => array('GET', 'POST'),
            'controller' => array(
                'class' => SubmissionHandler::class,
                'method' => 'form_submission_node'
            ),
            'access' => array(
                'administrator',
                'authenticated',
                'content_creator',
                'manager',
                'anonymous'
            ),
        )
    );
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
        $menus['system.form_builder.dashboard'] = &$menu_form_builder;

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
            $route['route_data']['path'] = "/admin/form-builder/{$form['name']}/settings";
            $route['route_data']['title'] = $form['title'];
            $ch_menu = new Menu($route);
            if (!empty(Route::fromRouteName('form_builder.form.'.$form['name']))) {
                $form_menu = new Menu('form_builder.form.'.$form['name']);
                $route_submission = Route::fromRouteName('form_builder.form.submission')->toArray();
                $route_submission['path'] = "/admin/form-builder/{$form['name']}/submission";
                $route_submission['title'] = "Submissions";
                $ch_menu->addChild($form_menu);
                $ch_menu->addChild(new Menu(['route_id' => 'form_builder.form.submission', 'route_data' => $route_submission]));
            }
            $list_menu->addChild($ch_menu);
        }
        $menu_form_builder->addChild($list_menu);
    }
   // dd($menus);

}


function form_builder_database_install()
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
    return array(
        'form_builder' => FormBuilderFieldBuilder::class,
    );
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
        ]
    ];
    return $library[$library_name] ?? [];
}
