<?php

namespace Simp\Core\modules\integration\rest;

use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\routes\Route;
use Symfony\Component\Yaml\Yaml;

class JsonRestManager
{
    protected array $rest_versions = [];
    protected string $version_storage = '';
    protected array $version_routes = [];
    protected string $version_routes_storage = '';
    protected array $data_providers = [];
    public function __construct() {
        $system_directory = new SystemDirectory();
        $this->version_storage = $system_directory->setting_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rest' . DIRECTORY_SEPARATOR . 'json';
        if (!is_dir($this->version_storage)) {
            @mkdir($this->version_storage, recursive: true);

        }
        $this->version_storage .= DIRECTORY_SEPARATOR . 'versions.yml';
        if (!file_exists($this->version_storage)) {
            @touch($this->version_storage);
        }

        $this->version_routes_storage = $system_directory->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'general';
        if (!is_dir($this->version_routes_storage)) {
            @mkdir($this->version_routes_storage, recursive: true);
        }
        $this->version_routes_storage .= DIRECTORY_SEPARATOR . 'general-routes.yml';
        if (!file_exists($this->version_routes_storage)) {
            @touch($this->version_routes_storage);
        }

        $dataSource = Caching::init()->get('default.admin.data-source');
        $custom_data_providers = $system_directory->setting_dir . DIRECTORY_SEPARATOR . 'integration' . DIRECTORY_SEPARATOR . 'data-source';
        if (!is_dir($custom_data_providers)) {
            @mkdir($custom_data_providers, recursive: true);
        }
        $custom_data_providers .= DIRECTORY_SEPARATOR . 'data-source.yml';
        if (!file_exists($custom_data_providers)) {
            @touch($custom_data_providers);
        }

        if (filesize($custom_data_providers) <= 0) {
            @copy($dataSource, $custom_data_providers);
        }

        $this->data_providers = Yaml::parseFile($custom_data_providers) ?? [];

        $this->version_routes = Yaml::parseFile($this->version_routes_storage) ?? [];
        $this->rest_versions = Yaml::parseFile($this->version_storage) ?? [];
    }
    public function addVersion(string $title, string $version_key): bool {
        $version_key = strtolower($version_key);
        $this->rest_versions[$version_key] = [
            'title' => $title,
            'version_key' => $version_key,
            'status' => 1
        ];
        return !empty(file_put_contents($this->version_storage, Yaml::dump($this->rest_versions)));
    }
    public function getVersion(string $version_key): ?array
    {
        if (isset($this->rest_versions[$version_key])) {
            return $this->rest_versions[$version_key];
        }
        return null;
    }
    public function getVersions(): array
    {
        return $this->rest_versions;
    }
    public function updateVersion(string $version_key, array $data): bool
    {
        if (isset($this->rest_versions[$version_key])) {
            $this->rest_versions[$version_key] = $data;
            return !empty(file_put_contents($this->version_storage, Yaml::dump($this->rest_versions)));
        }
        return false;
    }
    public function deleteVersion(string $version_key): bool
    {
        if (isset($this->rest_versions[$version_key])) {
            unset($this->rest_versions[$version_key]);
            return !empty(file_put_contents($this->version_storage, Yaml::dump($this->rest_versions)));
        }
        return false;
    }
    public function addVersionRoute(string $key, array $data): bool
    {
        $this->version_routes[$key] = $data;
        return !empty(file_put_contents($this->version_routes_storage, Yaml::dump($this->version_routes,Yaml::DUMP_OBJECT_AS_MAP)));
    }
    public function getVersionRoute(string $version_key): ?array {

        $found = array_filter($this->version_routes, function ($item) use ($version_key) {
            return isset($item['route_type']) && $item['route_type'] === "rest_". $version_key;
        });
        foreach ($found as $key => $route) {
            $found[$key] = new Route($key, $route);
        }
        return $found;
    }
    public function removeVersionRoute(string $route_key): bool
    {
        if (isset($this->version_routes[$route_key])) {
            unset($this->version_routes[$route_key]);
            return !empty(file_put_contents($this->version_routes_storage, Yaml::dump($this->version_routes,Yaml::DUMP_OBJECT_AS_MAP)));
        }
        return false;
    }

    public function addVersionRouteDataSourceSetting(string $route_key, string $handler): bool
    {
        $system_directory = new SystemDirectory();
        @mkdir($this->version_storage = $system_directory->setting_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rest' . DIRECTORY_SEPARATOR . 'json',recursive: true);

        $this->version_storage .= DIRECTORY_SEPARATOR . 'data-source-settings.yml';
        if (!file_exists($this->version_storage)) {
            @touch($this->version_storage);
        }
        $post_settings = Yaml::parseFile($this->version_storage) ?? [];
        $post_settings[$route_key] = $handler;
        return !empty(file_put_contents($this->version_storage, Yaml::dump($post_settings, Yaml::DUMP_OBJECT_AS_MAP)));
    }

    public function getVersionRouteDataSourceSetting(string $route_key): ?string
    {
        $system_directory = new SystemDirectory();
        $this->version_storage = $system_directory->setting_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rest' . DIRECTORY_SEPARATOR . 'json';
        if (!is_dir($this->version_storage)) {
            @mkdir($this->version_storage, recursive: true);
        }
        $this->version_storage .= DIRECTORY_SEPARATOR . 'data-source-settings.yml';
        if (!file_exists($this->version_storage)) {
            @touch($this->version_storage);
        }
        $post_settings = Yaml::parseFile($this->version_storage) ?? [];
        return $post_settings[$route_key] ?? null;
    }

    public function getRestVersions(): array
    {
        return $this->rest_versions;
    }

    public function getVersionStorage(): string
    {
        return $this->version_storage;
    }

    public function getVersionRoutes(): array
    {
        return $this->version_routes;
    }

    public function getVersionRoutesStorage(): string
    {
        return $this->version_routes_storage;
    }

    public function getDataProviders(): array
    {
        return $this->data_providers;
    }

    public static function factory(): JsonRestManager {
        return new JsonRestManager();
    }
}