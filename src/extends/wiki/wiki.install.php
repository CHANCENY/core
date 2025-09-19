<?php

use Simp\Core\extends\wiki\src\Controller\WikiController;
use Simp\Core\modules\menu\Menu;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\structures\taxonomy\VocabularyManager;

function wiki_database_install(): void
{
    // Create Vocabulary
    /** @var VocabularyManager $vocabulary */
    $vocabulary = Service::get(VocabularyManager::class);
    $vocabulary->addVocabulary("Wiki");

    $conn = Service::get('connection');

    $tables = [
        // Main wiki table
        "CREATE TABLE IF NOT EXISTS wiki_entities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            status ENUM('draft','published','archived') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",

        // Revisions table
        "CREATE TABLE IF NOT EXISTS wiki_entities_revision (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wiki_id INT NOT NULL,
            content LONGTEXT NOT NULL,
            status ENUM('draft','published','archived') DEFAULT 'draft',
            uid INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (wiki_id),
            FOREIGN KEY (wiki_id) REFERENCES wiki_entities(id) ON DELETE CASCADE,
            FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
        )",

        // Pivot table for wiki tags
        "CREATE TABLE IF NOT EXISTS wiki_entity_tags (
            wiki_id INT NOT NULL,
            tid INT NOT NULL,
            PRIMARY KEY (wiki_id, tid),
            INDEX (wiki_id),
            INDEX (tid),
            FOREIGN KEY (wiki_id) REFERENCES wiki_entities(id) ON DELETE CASCADE,
            FOREIGN KEY (tid) REFERENCES term_data(id) ON DELETE CASCADE
        )",

        // Pivot table for wiki authors
        "CREATE TABLE IF NOT EXISTS wiki_authors (
            wiki_id INT NOT NULL,
            uid INT NOT NULL,
            PRIMARY KEY (wiki_id, uid),
            INDEX (wiki_id),
            INDEX (uid),
            FOREIGN KEY (wiki_id) REFERENCES wiki_entities(id) ON DELETE CASCADE,
            FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
        )"
    ];

    foreach ($tables as $sql) {
        $conn->exec($sql);
    }
}

function wiki_library_install(string $library_name): array
{
    return [
        'wiki.library' => [
            'head' => [
                '/core/modules/wiki/assets/wiki-main.css'
            ],
            'footer' => [
                '/core/modules/wiki/assets/wiki-main.js'
            ]
        ],
        'wiki.editor' => [
            'head' => [
                '/core/modules/wiki/assets/wiki-main.css',
                '/core/modules/wiki/assets/wiki-editor.css'
            ],
            'footer' => [
                '/core/modules/wiki/assets/wiki-editor.js'
            ]
        ],

    ][$library_name];
}

/**
 * @throws \DI\DependencyException
 * @throws \DI\NotFoundException
 */
function wiki_template_install(): array
{
    $module = Service::get(\Simp\Core\components\extensions\ModuleHandler::class)->getModule('wiki');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

function wiki_route_install(): array
{

    return array(
        'wiki.entries' => array(
            'title' => 'Wiki Entries',
            'path' => '/wiki/entries',
            'method' => array(
                'GET',
                'POST'
            ),
            'controller' => array(
                'class' => WikiController::class,
                'method' => 'entries'
            ),
            'access' => array(
                'administrator',
                'authenticated',
                'anonymous',
                'content_creator',
                'manager',
                'editor',
                 'author',
                 'subscriber',
                 'contributor',
                 'moderator',
                 'reviewer',
                 'publisher',
                 'analyst',
                'support',
                'guest'
            ),
            'options' => array(
                'classes' => ['fa-brands fa-wikipedia-w']
            )
        ),
        'wiki.tag.entries' => array(
            'title' => 'Wiki Tag Entries',
            'path' => '/wiki/[id:int]',
            'method' => array(
                'GET',
                'POST'
            ),
            'controller' => array(
                'class' => WikiController::class,
                'method' => 'tag_entries'
            ),
            'access' => array(
                'administrator',
                'authenticated',
                'anonymous',
                'content_creator',
                'manager',
                'editor',
                'author',
                'subscriber',
                'contributor',
                'moderator',
                'reviewer',
                'publisher',
                'analyst',
                'support',
                'guest'
            ),
            'options' => array()
        ),
        'wiki.search' => array(
            'title' => 'Wiki Search',
            'path' => '/wiki/search',
            'method' => array(
                'GET',
                'POST'
            ),
            'controller' => array(
                'class' => WikiController::class,
                'method' => 'search'
            ),
            'access' => array(
                'administrator',
                'authenticated',
                'anonymous',
                'content_creator',
                'manager',
                'editor',
                'author',
                'subscriber',
                'contributor',
                'moderator',
                'reviewer',
                'publisher',
                'analyst',
                'support',
                'guest'
            ),
            'options' => array()
        ),
        'wiki.entry' => array(
            'title' => 'Wiki Entry',
            'path' => '/wiki/[slug:string]',
            'method' => array(
                'GET',
                'POST'
            ),
            'controller' => array(
                'class' => WikiController::class,
                'method' => 'entry'
            ),
            'access' => array(
                'administrator',
                'authenticated',
                'anonymous',
                'content_creator',
                'manager',
                'editor',
                'author',
                'subscriber',
                'contributor',
                'moderator',
                'reviewer',
                'publisher',
                'analyst',
                'support',
                'guest'
            ),
            'options' => array()
        ),
        'wiki.revision.add' => [
            'title' => 'Wiki Revision Add',
            'path' => '/wiki/revision/add',
            'method' => [
                'GET',
                'POST'
            ],
            'controller' => [
                'class' => WikiController::class,
                'method' => 'revision_add'
            ],
            'access' => [
                'administrator',
                'content_creator',
                'manager',
                'editor',
                'author',
                'subscriber',
                'contributor',
                'moderator',
                'reviewer',
                'publisher',
                'analyst',
                'support',
            ]
        ],
        'wiki.create' => array(
            'title' => 'Wiki Create',
            'path' => '/wiki/create/add',
            'method' => array(
                'GET',
                'POST'
            ),
            'controller' => array(
                'class' => WikiController::class,
                'method' => 'create'
            ),
            'access' => [
                'administrator',
                'content_creator',
                'manager',
                'editor',
                'author',
                'subscriber',
                'contributor',
                'moderator',
                'reviewer',
                'publisher',
                'analyst',
                'support',
            ],
            'options' => array(
                'classes' => ['fa','fa-plus']
            )
        ),
        'wiki.tag.create' => array(
            'title' => 'Wiki Tag Create',
            'path' => '/wiki/tag/create',
            'method' => array(
                'GET',
                'POST'
            ),
            'controller' => array(
                'class' => WikiController::class,
                'method' => 'tag_create'
            ),
            'access' => [
                'administrator',
                'content_creator',
                'manager',
                'editor',
                'author',
                'subscriber',
                'contributor',
                'moderator',
                'reviewer',
                'publisher',
                'analyst',
                'support',
            ]
        )
    );
}

function wiki_menu_install(array &$menus): void
{
    if (!empty($menus['system.content'])) {

        $menu = new Menu('wiki.entries');
        $menu->addChild(new Menu('wiki.create'));
        $menus['system.content']->addChild($menu);
    }
}