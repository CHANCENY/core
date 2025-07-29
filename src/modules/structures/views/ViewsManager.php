<?php

namespace Simp\Core\modules\structures\views;

use Simp\Core\lib\controllers\ViewsController;
use Simp\Core\lib\installation\SystemDirectory;
use Symfony\Component\Yaml\Yaml;

class ViewsManager extends SystemDirectory
{
    protected array $views = [];
    protected string $view = '';
    protected array $pages = [];

    public function __construct() {

        parent::__construct();
        $this->view = $this->setting_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'views';
        if (!is_dir($this->view)) {
            @mkdir($this->view);
        }
        $list = array_diff(scandir($this->view) ?? [], ['.', '..']);
        foreach ($list as $file) {
            $full_path = $this->view . DIRECTORY_SEPARATOR . $file;
            if (file_exists($full_path) && !is_dir($full_path)) {
                $name = pathinfo($full_path, PATHINFO_FILENAME);
                $this->views[$name] = Yaml::parseFile($full_path);
            }
        }
        if ($this->views) {
            foreach ($this->views as $name => $view) {
                foreach ($view['displays'] as $k=>$display) {
                    $full_path = $this->view . DIRECTORY_SEPARATOR . 'view-display' . DIRECTORY_SEPARATOR . $display. '.yml';
                    if (file_exists($full_path)) {
                        $this->views[$name]['displays'][$k] = Yaml::parseFile($full_path);
                    }
                }
            }
        }
        $views_routes = $this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'views-routes.yml';
        if (file_exists($views_routes)) {
            $this->pages = Yaml::parseFile($views_routes) ?? [];
        }
    }

    public function getViews(): array
    {
        return $this->views;
    }

    public function getView(string $name): array
    {
        return $this->views[$name] ?? [];
    }

    public function addView(string $name, array $view): bool
    {
        $view['machine_name'] = $name;
        $this->views[$name] = $view;
        $view_path = $this->view . DIRECTORY_SEPARATOR . $name . '.yml';
        return !empty(file_put_contents($view_path, Yaml::dump($view, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)));
    }

    public function removeView(string $name): bool
    {
        $view = $this->getView($name);
        if ($view['displays']) {
            foreach ($view['displays'] as $display) {
                $full_path = $this->view . DIRECTORY_SEPARATOR . 'view-display' . DIRECTORY_SEPARATOR . $display['display_name'] . '.yml';
                $routes = $this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR. 'views'. DIRECTORY_SEPARATOR . 'views-routes.yml';
                if (file_exists($routes)) {
                    $route_data = Yaml::parseFile($routes);
                    if (isset($route_data[$display['display_name']])) {
                        unset($route_data[$display['display_name']]);
                        file_put_contents($routes, Yaml::dump($route_data));
                    }
                }
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
        }
        return @unlink($this->view . DIRECTORY_SEPARATOR . $name . '.yml');
    }

    public static function viewsManager(): ViewsManager
    {
        return new ViewsManager();
    }

    public function addViewDisplay(mixed $view_name, array $display): bool
    {
        $display_path = $this->view . DIRECTORY_SEPARATOR . 'view-display';
        if (!is_dir($display_path)) {
            @mkdir($display_path);
        }

        $display_name = "views_{$view_name}_{$display['display_name']}";
        $display['display_name'] = $display_name;
        $route_id = "{$display['display_name']}";

        $route = [
            'title' => $display['name'],
            'path' => $display['display_url'],
            'route_type' => 'views',
            'method' => [
                'GET',
                'POST',
                'PUT',
                'OPTIONS',
                'DELETE',
            ],
            'controller' => [
                'class' => ViewsController::class,
                'method' => 'views_entry_controller'
            ],
            'access' => is_array($display['permission']) ? $display['permission'] : [$display['permission']],
        ];

        $route_path = $this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'views-routes.yml';
        if (file_exists($route_path)) {
            $routes = Yaml::parseFile($route_path) ?? [];
            $routes[$route_id] = $route;
            file_put_contents($route_path, Yaml::dump($routes,Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            unset($display['display_url']);
            $display['view'] = $view_name;
            file_put_contents($display_path .DIRECTORY_SEPARATOR. $display_name. '.yml', Yaml::dump($display,Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            $view = $this->getView($view_name);
            $old = array_map(function ($display) { return $display['display_name'] ?? null; },$view['displays']);
            $old = array_filter($old);
            $view['displays'] = array_values($old);
            $view['displays'][] = $display_name;
            $view['displays'] = array_unique($view['displays']);
            $view['view'] = $view_name;
            return $this->addView($view_name, $view);
        }

        return false;
    }

    public function getDisplay(string $name): array
    {
        $full_path = $this->view . DIRECTORY_SEPARATOR . 'view-display' . DIRECTORY_SEPARATOR . $name . '.yml';
        if (file_exists($full_path)) {
            return Yaml::parseFile($full_path) ?? [];
        }
        return [];
    }

    public function removeDisplay(string $view_name, string $name): bool {
        $view = $this->getView($view_name);
        if ($view['displays']) {
            foreach ($view['displays'] as $key=>$display) {
                if ($display['display_name'] === $name) {
                    unset($view['displays'][$key]);
                    $routes = $this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR . 'views-routes.yml';
                    if (file_exists($routes)) {
                        $routes_data = Yaml::parseFile($routes) ?? [];
                        unset($routes_data[$name]);
                        file_put_contents($routes, Yaml::dump($routes_data,Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
                    }
                }
            }
            $this->addView($view_name, $view);
            $line_file = $this->view. DIRECTORY_SEPARATOR . 'view-display'. DIRECTORY_SEPARATOR.$name. '.yml';
            return @unlink($line_file);
        }
        return true;
    }

    public function addFieldDisplay(mixed $display_name, array $display): bool
    {
        $display_path = $this->view . DIRECTORY_SEPARATOR . 'view-display'. DIRECTORY_SEPARATOR . $display_name . '.yml';
        return file_put_contents($display_path, Yaml::dump($display, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    /**
     * @param string $display_name
     * @param string $key comprise with content_type_name|field_name
     * @param string $section this could be fields, sort_criteria, filter_criteria.
     * @return bool
     */
    public function removeDisplayFieldSetting(string $display_name, string $key, string $section): bool
    {
        $display = $this->getDisplay($display_name);
        if (!empty($display[$section][$key])) {
            unset($display[$section][$key]);
            return $this->addFieldDisplay($display_name, $display);
        }
        return true;
    }
}
