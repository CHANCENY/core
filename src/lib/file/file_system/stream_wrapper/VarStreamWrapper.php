<?php

namespace Simp\Core\lib\file\file_system\stream_wrapper;

class VarStreamWrapper extends TopStreamWrapper {
    public function __construct()
    {
        parent::__construct();
        $this->base_path = $this->system->var_dir;
        $this->stream_name = 'var';
        $GLOBALS['stream_wrapper']['var'] = $this;
    }
}