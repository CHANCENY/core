<?php

namespace Simp\Core\modules\files\uploads;

use Simp\Core\modules\files\traits\FileTraits;
use Simp\Uploader\upload_system\uploader\UrlUploader\UrlUploader;

class UrlUpload extends UrlUploader
{
    use FileTraits;
}