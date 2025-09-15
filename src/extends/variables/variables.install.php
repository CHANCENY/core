<?php

function variables_database_install(): bool {
    $query = "CREATE TABLE IF NOT EXISTS `environment_variables` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE)";
    \Simp\Core\modules\database\Database::database()->con()->exec($query);
    return true;
}

function variables_template_install(): array
{
    $module = \Simp\Core\components\extensions\ModuleHandler::factory()->getModule('variables');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

// install routes
function variables_route_install()
{
    return array(
        'variables.dashboard.route' => array(
            'title' => 'System Variables',
            'path' => '/admin/variables',
            'method' => array('GET', 'POST', 'PUT', 'DELETE'),
            'controller' => array(
                'class' => \Simp\Core\extends\variables\src\Controller\EnvironmentVariableController::class,
                'method' => 'index'
            ),
            'access' => array(
                'administrator'
            ),
            'options' => array(
                'classes' => ['fa','fa-gear']
            )
        )
    );
}

// register a menu item
function variables_menu_install(array &$menus) {
    $menus['system.config']->addChild(new \Simp\Core\modules\menu\Menu('variables.dashboard.route'));
}

// install library
function variables_library_install(string $library_name): array {
    $library = [
        'variables.library' => [
            'head' => [
                ''
            ],
            'footer' => [
                '/core/modules/variables/assets/variables.js',
            ]
        ]
    ];
    return $library[$library_name] ?? [];
}