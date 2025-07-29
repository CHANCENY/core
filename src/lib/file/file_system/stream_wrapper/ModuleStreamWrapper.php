<?php

namespace Simp\Core\lib\file\file_system\stream_wrapper;

class ModuleStreamWrapper extends TopStreamWrapper
{
    public function __construct()
    {
        parent::__construct();
        $this->base_path = $this->system->module_dir;
        $this->stream_name = 'module';
        $GLOBALS['stream_wrapper']['module'] = $this;
    }
}