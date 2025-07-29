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
        $root = php_sapi_name() == "cli" ? getcwd() : dirname(getcwd(),1);
        $this->root_dir = $root;
        $this->webroot_dir = $root . DIRECTORY_SEPARATOR . "public";
        $this->var_dir = $root . DIRECTORY_SEPARATOR . "public".DIRECTORY_SEPARATOR."sites".DIRECTORY_SEPARATOR."var";
        $this->public_dir = $root . DIRECTORY_SEPARATOR . "public".DIRECTORY_SEPARATOR."sites".DIRECTORY_SEPARATOR."public";
        $this->private_dir = $root . DIRECTORY_SEPARATOR . "public".DIRECTORY_SEPARATOR."sites".DIRECTORY_SEPARATOR."private";
        $this->global_dir = $root.DIRECTORY_SEPARATOR."public".DIRECTORY_SEPARATOR."sites";
        $this->setting_dir = $root . DIRECTORY_SEPARATOR . "public".DIRECTORY_SEPARATOR."sites".DIRECTORY_SEPARATOR."configs";
        $this->module_dir = $root . DIRECTORY_SEPARATOR . "public".DIRECTORY_SEPARATOR."module";
        $this->theme_dir = $root . DIRECTORY_SEPARATOR . "public".DIRECTORY_SEPARATOR."theme";
    }

    public function toArray()
    {
        return get_object_vars($this);
    }
}