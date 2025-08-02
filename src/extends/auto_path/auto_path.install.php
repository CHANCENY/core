<?php

// Declaration of need hooks for creating auto path schema and templates

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\auto_path\src\controller\AutoPathController;
use Simp\Core\extends\auto_path\src\path\AutoPathCronSubscriber;
use Simp\Core\modules\database\Database;

function auto_path_database_install(): bool
{
    $query = "CREATE TABLE IF NOT EXISTS `auto_path` (id INT AUTO_INCREMENT PRIMARY KEY, path VARCHAR(400) NOT NULL UNIQUE, nid INT NOT NULL UNIQUE, pattern_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_nid FOREIGN KEY (nid) REFERENCES node_data(nid) ON DELETE CASCADE, CONSTRAINT fk_id FOREIGN KEY (pattern_id) REFERENCES auto_path_patterns(id) ON DELETE CASCADE)";
    $query1 = "CREATE TABLE IF NOT EXISTS `auto_path_patterns` (id INT AUTO_INCREMENT PRIMARY KEY, pattern_path VARCHAR(255) NOT NULL UNIQUE, route_controller VARCHAR(255) NOT NULL, entity_type VARCHAR(255) NOT NULL UNIQUE)";
    Database::database()->con()->prepare($query1)->execute();
    return Database::database()->con()->prepare($query)->execute();
}

function auto_path_template_install(): array {
    $module = ModuleHandler::factory()->getModule('auto_path');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

function auto_path_route_install(): array
{
    return [
        'auto_path.create' => [
            'title' => 'Auto Path Create',
            'path' => '/admin/auto-path/create',
            'method' => [
                'GET',
                'POST'
            ],
            'controller' => [
                'class' => AutoPathController::class,
                'method' => 'auto_path_create'
            ],
            'access' => [
                'administrator',
            ]
        ],
        'auto_path.list' => [
            'title' => 'Auto Path List',
            'path' => '/admin/auto-path/list',
            'method' => [
                'GET'
            ],
            'controller' => [
                'class' => AutoPathController::class,
                'method' => 'auto_path_list'
            ],
            'access' => [
                'administrator',
            ]
        ],
        'auto_path.delete' => [
            'title' => 'Auto Path Delete ',
            'path' => '/admin/auto-path/[id:int]/delete',
            'method' => [
                'GET'
            ],
            'controller' => [
                'class' => AutoPathController::class,
                'method' => 'auto_path_delete'
            ],
            'access' => [
                'administrator',
            ]
        ]
    ];

}

function auto_path_cron_jobs_install(): array
{
    return [
        'auto_path_populate' => [
            'title' => 'Auto Path Alias Creation',
            'description' => 'Auto Path cron that will do the creation of aliases for those node that are not yet have alias',
            'timing' => 'every|day',
            'subscribers' => 'auto_path.alias'
        ]
    ];
}

function auto_path_cron_subscribers_install(): array
{
    return [
        'auto_path.alias' => AutoPathCronSubscriber::class,
    ];
}