<?php

namespace Simp\Core\lib\file\file_system\stream_wrapper;

class PrivateStreamWrapper extends TopStreamWrapper
{
    public function __construct()
    {
        parent::__construct();
        $this->base_path = $this->system->private_dir;
        $this->stream_name = 'private';
        $GLOBALS['stream_wrapper']['private'] = $this;
    }
}