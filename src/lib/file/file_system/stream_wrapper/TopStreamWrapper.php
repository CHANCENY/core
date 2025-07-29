<?php

namespace Simp\Core\lib\file\file_system\stream_wrapper;

use Simp\Core\lib\installation\InstallerValidator;
use Simp\StreamWrapper\Stream\StreamWrapper;

class TopStreamWrapper extends StreamWrapper
{
    protected InstallerValidator $system;
    public function __construct()
    {
        $this->system = $GLOBALS['system_store'];
        parent::__construct();
    }
}