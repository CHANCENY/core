<?php

namespace Simp\Core\lib\file\file_system\stream_wrapper;

class PublicStreamWrapper extends TopStreamWrapper
{
    public function __construct()
    {
        parent::__construct();
        $this->base_path = $this->system->public_dir;
        $this->stream_name = 'public';
        $GLOBALS['stream_wrapper']['public'] = $this;
    }
}