<?php

namespace Simp\Core\modules\pages;

use DOMDocument;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\user\roles\RolesRepository;
use ValueError;

class PageLoader
{
    protected array $pages = [];

    protected SystemDirectory $systemDirectory;
    private array $routes = [];

    public function __construct()
    {
        $this->systemDirectory = Service::get(SystemDirectory::class);

        $static = $this->systemDirectory->webroot_dir . DIRECTORY_SEPARATOR . 'static';

        if (!is_dir($static)) {
            mkdir($static);
        }

        $files = $this->loadPages($static);

        $routes = [];

        $roles = new RolesRepository($this->systemDirectory);


        if (!empty($files)) {
            foreach ($files as $key => $value) {

                if (is_array($value)) {
                    foreach ($value as $k=>$item) {

                        $path = $item['path'];
                        $title = $item['title'];

                        $route_id = $key. ".".$k;

                        $extension = pathinfo($path, PATHINFO_EXTENSION);

                        if ($extension === 'html' || $extension === 'htm') {
                            // rename file $path.html or $path.htm to $path.twig
                            $new_path = preg_replace('/\.(html|htm)$/i', '.twig', $path);
                            rename($path, $new_path);
                            $path = $new_path;
                            $extension = 'twig';
                        }

                        if ($k === 0) {

                            // this is index
                            $routes[$route_id] = [
                                'title' => $title,
                                'path' => "/$key",
                                'controller' => [
                                    'class' => PageRouteController::class,
                                    'method' => 'index',
                                ],
                                'method' => ['GET', 'POST', 'DELETE', 'PUT', 'PATCH'],
                                'access' => $roles->getRoles(),
                                'options' => [
                                    'is_php' => $extension === 'php',
                                    'is_twig' => $extension === 'twig',
                                    'file' => $path
                                ]

                            ];

                            continue;

                        }

                        // extract this $path from $key to end
                        $list = explode($key,$path);
                        $web_path = end($list);
                        $web_path = ltrim($web_path, '/');
                        $web_path = "/$key/$web_path";

                        // remove from $web_path the .php or .html or .htm extension
                        $web_path = preg_replace('/\.(php|html|htm|twig)$/i', '', $web_path);
                        $web_path = ltrim($web_path, '/');
                        $web_path = trim($web_path);

                        $routes[$route_id] = [
                            'title' => $title,
                            'path' => "/$web_path",
                            'controller' => [
                                'class' => PageRouteController::class,
                                'method' => 'index',
                            ],
                            'method' => ['GET', 'POST', 'DELETE', 'PUT', 'PATCH'],
                            'access' => $roles->getRoles(),
                            'options' => [
                                'is_php' => $extension === 'php',
                                'is_twig' => $extension === 'twig',
                                'file' => $path
                            ]
                        ];

                    }
                }

            }
        }


        $this->routes = $routes;

        $markdown_pages = "";
        foreach ($this->routes as $route) {
            $markdown_pages .= "- [{$route['title']}]({$route['path']})\n";
        }

        $readme = $static . DIRECTORY_SEPARATOR . 'README.md';
        $content = <<<MARKDOWN
# STATIC DIRECTORY 

### Usage
- This directory is used to store static files like images, css, js, etc.
- This directory is accessible from the web root.
- This is used by simple CMS to store static pages

### How to indicate directory in static directory contains static pages
> Create a file named `index.html` in the directory.

### Example pages on this directory
{$markdown_pages}

MARKDOWN;

        file_put_contents($readme, $content);
    }

    protected function loadPages(string $directory): array
    {
        $loaded_files = [];

        if (!is_dir($directory)) {
            return $loaded_files;
        }

        // Scan the directory
        $items = scandir($directory);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            // If it's a directory, handle it recursively
            if (is_dir($path)) {
                $index_file = '';
                $indexFiles = ['index.html', 'index.htm', 'index.php', 'index.twig'];
                $hasIndex = false;

                // Check if directory has an index file
                foreach ($indexFiles as $indexFile) {
                    if (file_exists($path . DIRECTORY_SEPARATOR . $indexFile)) {
                        $index_file = $path . DIRECTORY_SEPARATOR . $indexFile;
                        $hasIndex = true;
                        break;
                    }
                }

                if ($hasIndex) {
                    // Load files inside this directory (excluding index files)
                    $dirFiles = [];
                    $title = self::extractTitle($index_file) ?? pathinfo($index_file, PATHINFO_FILENAME);
                    $dirFiles[] = [
                        'title' => $title,
                        'path'  => $index_file,
                    ];
                    foreach (scandir($path) as $inner) {
                        if ($inner === '.' || $inner === '..' || in_array($inner, $indexFiles)) {
                            continue;
                        }

                        $innerPath = $path . DIRECTORY_SEPARATOR . $inner;
                        if (is_file($innerPath) && preg_match('/\.(html?|php|twig)$/i', $inner)) {
                            $title = self::extractTitle($innerPath) ?? pathinfo($inner, PATHINFO_FILENAME);
                            $dirFiles[] = [
                                'title' => $title,
                                'path'  => $innerPath,
                            ];
                        }
                    }

                    $dirName = basename($path);
                    $loaded_files[$dirName] = $dirFiles;
                } else {
                    // If no index, recurse deeper
                    $loaded_files = array_merge($loaded_files, $this->loadPages($path));
                }
            }

            // If it's a file in the root directory
            elseif (is_file($path) && preg_match('/\.(html?|php|twig)$/i', $item)) {
                $title = self::extractTitle($path) ?? pathinfo($item, PATHINFO_FILENAME);
                $loaded_files[pathinfo($item, PATHINFO_FILENAME)] = [
                    'title' => $title,
                    'path'  => $path,
                ];
            }
        }

        return $loaded_files;
    }

    /**
     * Extracts the <title> tag from an HTML file.
     */
    protected static function extractTitle(string $filePath): ?string
    {
        if (!is_readable($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            // File empty or unreadable
            return null;
        }

        $dom = new DOMDocument();

        // Suppress HTML5 or invalid markup warnings
        libxml_use_internal_errors(true);
        try {
            $dom->loadHTML($content, LIBXML_NOWARNING | LIBXML_NOERROR);
        } catch (ValueError $e) {
            // In case of empty or invalid HTML, just return null
            libxml_clear_errors();
            libxml_use_internal_errors(false);
            return null;
        }

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $titleTags = $dom->getElementsByTagName('title');
        if ($titleTags->length > 0) {
            return trim($titleTags->item(0)->textContent);
        }

        return null;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

}