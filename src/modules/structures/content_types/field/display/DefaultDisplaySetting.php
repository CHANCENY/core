<?php

namespace Simp\Core\modules\structures\content_types\field\display;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\themes\View;
use Simp\Fields\FieldBase;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

trait DefaultDisplaySetting
{

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function display(string $field_type, FieldBase $field, array $context): string
    {
        $template = match ($field_type) {
            'text' => 'default.view.basic.text',
            'textarea' => 'default.view.node.field.textarea',
            'url' => 'default.view.node.field.url',
            'file' => 'default.view.node.field.file',
            'reference', 'drag_and_drop' => 'default.view.node.reference.link',
            'checkbox' => 'default.view.node.field.checkbox',
        };
        $context = ['definition'=>$field, ...$context];
        return trim(View::view($template, $context));
    }
}