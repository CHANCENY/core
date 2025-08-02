<?php

use Twig\TwigFunction;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\announcement\src\controller\AnnouncementController;
use Simp\Core\extends\announcement\src\plugin\Announcement;
use Simp\Core\modules\database\Database;

function announcement_route_install(): array
{
    return [
        'announcement.notifications' => [
            'title' => 'Announcement Notifications',
            'path' => '/admin/announcement/notifications',
            'method' => [
                'GET',
                'POST'
            ],
            'controller' => [
                'class' => AnnouncementController::class,
                'method' => 'announcement_notifications'
            ],
            'access' => [
                'administrator',
                'content_creator',
                'manager',
                'authenticated'
            ]
        ],
        'announcement.support.window' => [
            'title' => 'Support Window',
            'path' => '/admin/announcement/support-window',
            'method' => [
                'GET',
                'POST'
            ],
            'controller' => [
                'class' => AnnouncementController::class,
                'method' => 'announcement_support_window'
            ],
            'access' => [
                'administrator',
                'content_creator',
                'manager',
            ]
        ],
    ];
}

function announcement_database_install(): bool
{
    $query = "CREATE TABLE IF NOT EXISTS `announcement` (id INT AUTO_INCREMENT PRIMARY KEY, 
title VARCHAR(255), 
owner_uid INT NOT NULL,
 to_uid INT NOT NULL, 
 content TEXT,
 status INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `announcement_owner_uid` FOREIGN KEY (`owner_uid`) REFERENCES `users` (`uid`) ON DELETE CASCADE ON UPDATE CASCADE)";

    $query = Database::database()->con()->prepare($query);
    return $query->execute();
}

function announcement_template_install(): array
{
    $module = ModuleHandler::factory()->getModule('announcement');
    $path = $module['path'] ?? __DIR__;

    // Directory where your templates are located
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

function announcement_twig_function_install(): array
{
    return [
        new TwigFunction('announcement_unread_count', function () {
            return Announcement::factory()->getUnreadAnnouncementsCount();
        })
    ];
}
