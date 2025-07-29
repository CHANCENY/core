<?php

namespace Simp\Core\lib\file\file_system\stream_wrapper;

class ThemeStreamWrapper extends TopStreamWrapper
{
    public function __construct()
    {
        parent::__construct();
        $this->base_path = $this->system->theme_dir;
        $this->stream_name = 'theme';
        $GLOBALS['stream_wrapper']['theme'] = $this;
    }
}