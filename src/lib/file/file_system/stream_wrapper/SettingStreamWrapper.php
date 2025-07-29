<?php

namespace Simp\Core\lib\file\file_system\stream_wrapper;

class SettingStreamWrapper extends TopStreamWrapper
{
    public function __construct()
    {
        parent::__construct();
        $this->base_path = $this->system->setting_dir;
        $this->stream_name = 'setting';
        $GLOBALS['stream_wrapper']['setting'] = $this;
    }
}