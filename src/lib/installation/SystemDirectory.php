<?php

namespace Simp\Core\lib\installation;

class SystemDirectory
{
    public string $root_dir;
    public string $webroot_dir;
    public string $setting_dir;
    public string $var_dir;
    public string $global_dir;
    public string $public_dir;
    public string $private_dir;
    public string $theme_dir;
    public string $module_dir;

    public function __construct()
    {
        $this->root_dir = $this->findRoot();
        $this->webroot_dir   = $this->root_dir . DIRECTORY_SEPARATOR . "public";
        $this->var_dir       = $this->join($this->webroot_dir, "sites", "var");
        $this->public_dir    = $this->join($this->webroot_dir, "sites", "public");
        $this->private_dir   = $this->join($this->webroot_dir, "sites", "private");
        $this->global_dir    = $this->join($this->webroot_dir, "sites");
        $this->setting_dir   = $this->join($this->webroot_dir, "sites", "configs");
        $this->module_dir    = $this->join($this->webroot_dir, "module");
        $this->theme_dir     = $this->join($this->webroot_dir, "theme");
    }

    /**
     * Attempt to locate the application root directory by searching for 'vendor' folder.
     */
    private function findRoot(int $maxDepth = 50): string
    {
        $root = __DIR__;

        for ($i = 0; $i < $maxDepth; $i++) {
            if (is_dir($root . DIRECTORY_SEPARATOR . 'vendor')) {
                return $root;
            }

            $parent = dirname($root);
            if ($parent === $root) {
                break; // Reached filesystem root
            }

            $root = $parent;
        }

        throw new \RuntimeException("Unable to locate application root. 'vendor' directory not found.");
    }

    /**
     * Join multiple path segments into a proper path using DIRECTORY_SEPARATOR.
     */
    public function join(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Export all path properties as an associative array.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Optional: Return missing directories
     */
    public function validateDirectories(): array
    {
        $missing = [];
        foreach ($this->toArray() as $key => $path) {
            if (!is_dir($path)) {
                $missing[] = $key;
            }
        }
        return $missing;
    }

    /**
     * Optional: Return object as JSON string for debugging
     */
    public function __toString(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
