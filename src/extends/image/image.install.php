<?php

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\image\src\controller\ImageController;
use Simp\Core\extends\image\src\field\FieldImageGalleryBuilder;
use Simp\Core\modules\database\Database;


/**
 * Installs the database table required for the image gallery functionality.
 * Creates a table named 'image_gallery' with an auto-incrementing ID and
 * foreign ID (fid) column if it doesn't already exist.
 *
 * @return bool Returns true if table creation was successful, false otherwise
 */
function image_database_install(): bool
{
    $query = "CREATE TABLE IF NOT EXISTS `image_gallery` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fid` INT NOT NULL,
  FOREIGN KEY (`fid`) REFERENCES `file_managed` (`fid`) ON DELETE CASCADE
);
";
    $query = Database::database()->con()->prepare($query);
    return $query->execute();
}

/**
 * Retrieves the installation paths for image templates.
 *
 * @return array Returns an array containing the directory paths for the image templates.
 */
function image_template_install(): array
{
    $module = ModuleHandler::factory()->getModule('image');
    $path = $module['path'] ?? __DIR__;
    return [
        $path . DIRECTORY_SEPARATOR . 'templates'
    ];
}

function image_field_install(): array
{
    return [
        'image_gallery' => FieldImageGalleryBuilder::class,
    ];
}

function image_library_install(string $library_name): array
{
    $library = [
        'image.gallery.library' => [
            'head' => [
                '/core/extends/image/assets/css/style.css'
            ],
            'footer' => [
                '/core/extends/image/assets/js/placeholder.js',
                '/core/extends/image/assets/js/gallery.js'
            ]
        ]
    ];
    return $library[$library_name] ?? [];
}

function image_route_install(): array
{
    return [
        'image.gallery.loader' => [
            'title' => 'Galleries',
            'path' => '/gallery/loader/[page:int]',
            'method' => [
                'GET',
                'POST'
            ],
            'controller' => [
                'class' => ImageController::class,
                'method' => 'loader'
            ],
            'access' => [
                'administrator',
                'authenticated',
                'anonymous',
                'content_creator',
                'manager'
            ]
        ],
        'image.gallery.upload' => [
            'title' => 'Gallery Image Upload',
            'path' => '/gallery/upload',
            'method' => [
                'GET',
                'POST'
            ],
            'controller' => [
                'class' => ImageController::class,
                'method' => 'upload'
            ],
            'access' => [
                'administrator',
                'authenticated',
                'anonymous',
                'content_creator',
                'manager'
            ]
        ]
    ];
}