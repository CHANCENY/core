<?php

namespace Simp\Core\lib\file\file_system\stream_wrapper;

class GlobalStreamWrapper extends TopStreamWrapper
{
    public function __construct()
    {
        parent::__construct();
        $this->base_path = $this->system->global_dir;
        $this->stream_name = 'global';
        $GLOBALS['stream_wrapper']['global'] = $this;
    }
}