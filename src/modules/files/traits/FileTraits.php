<?php

namespace Simp\Core\modules\files\traits;

use Simp\Core\modules\files\uploads\FormUpload;
use Simp\Core\modules\files\uploads\UrlUpload;

trait FileTraits
{
    public static function uploadUrlImage(string $url, string $destination_name): UrlUpload
    {
        /**@var UrlUpload $uploader**/
        $uploader = new self();
        $uploader->addAllowedExtension('image/*');
        $uploader->addUrl($url);
        $uploader->validate()->moveFileUpload($destination_name);
        return $uploader;
    }

    public static function uploadUrlVideo(string $url, string $destination_name): UrlUpload
    {
        /**@var UrlUpload $uploader**/
        $uploader = new self();
        $uploader->addAllowedExtension('video/*');
        $uploader->addUrl($url);
        $uploader->validate()->moveFileUpload($destination_name);
        return $uploader;
    }

    public static function uploadUrlAudio(string $url, string $destination_name): UrlUpload
    {
        /**@var UrlUpload $uploader**/
        $uploader = new self();
        $uploader->addAllowedExtension('audio/*');
        $uploader->addUrl($url);
        $uploader->validate()->moveFileUpload($destination_name);
        return $uploader;
    }

    public static function uploadUrlFile(string $url, string $destination_name): UrlUpload
    {
        /**@var UrlUpload $uploader**/
        $uploader = new self();
        $uploader->addAllowedExtension('application/*');
        $uploader->addUrl($url);
        $uploader->validate()->moveFileUpload($destination_name);
        return $uploader;
    }

    public static function uploadFormImage(array $field_array, string $destination_name): FormUpload
    {
        /**@var FormUpload $uploader**/
        $uploader = new self();
        $uploader->addAllowedExtension('image/*');
        $uploader->addFileObject($field_array);
        $uploader->validate()->moveFileUpload($destination_name);
        return $uploader;
    }

    public static function uploadFormVideo(array $field_array, string $destination_name): FormUpload
    {
        /**@var FormUpload $uploader**/
        $uploader = new self();
        $uploader->addAllowedExtension('video/*');
        $uploader->addFileObject($field_array);
        $uploader->validate()->moveFileUpload($destination_name);
        return $uploader;
    }

    public static function uploadFormAudio(array $field_array, string $destination_name): FormUpload
    {
        /**@var FormUpload $uploader**/
        $uploader = new self();
        $uploader->addAllowedExtension('audio/*');
        $uploader->addFileObject($field_array);
        $uploader->validate()->moveFileUpload($destination_name);
        return $uploader;
    }

    public static function uploadFormFile(array $field_array, string $destination_name): FormUpload
    {
        /**@var FormUpload $uploader**/
        $uploader = new self();
        $uploader->addAllowedExtension('application/*');
        $uploader->addFileObject($field_array);
        $uploader->validate()->moveFileUpload($destination_name);
        return $uploader;
    }
}