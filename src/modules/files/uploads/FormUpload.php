<?php

namespace Simp\Core\modules\files\uploads;

use Simp\Core\modules\files\traits\FileTraits;
use Simp\Uploader\upload_system\uploader\UrlUploader\FormUploader;

class FormUpload extends FormUploader{
    use FileTraits;
}