<?php

namespace Simp\Core\modules\structures\content_types\form;

use Exception;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\components\reference_field\ReferenceField;
use Simp\Core\extends\auto_path\src\path\AutoPathAlias;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\uploads\FormUpload;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\entity\User;
use Simp\Core\modules\user\fields\UserReferenceField;
use Simp\Default\SelectField;
use Simp\Fields\FieldBase;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Simp\Core\modules\services\Service;

class ContentTypeDefinitionForm extends FormBase
{
    protected ?array $content_type = [];
    protected bool $validated = true;

    protected Request $request;

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $this->request = Service::serviceManager()->request;
        $content_name = $this->request->get("content_name");
        if (empty($content_name)) {
            $redirect = new RedirectResponse('/');
            Messager::toast()->addWarning("No content type name provided");
            $redirect->setStatusCode(302);
            $redirect->send();
        }

        $this->content_type = ContentDefinitionManager::contentDefinitionManager()->getContentType($content_name);
        if (empty($this->content_type)) {
            $redirect = new RedirectResponse('/');
            Messager::toast()->addWarning("No content type name provided");
            $redirect->setStatusCode(302);
            $redirect->send();
        }
    }

    public function getFormId(): string
    {
        return $this->content_type["machine_name"]. "_form_id";
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function buildForm(array $form): array
    {
        $form['title'] = [
            'type' => 'text',
            'label' => "Title",
            'required' => true,
            'class' => ['form-control'],
            'id' => 'title',
            'name' => 'title'
        ];
        $fields = $this->content_type["fields"] ?? [];
        $form = array_merge($form, $fields);
        $form['status'] = [
            'type' => 'select',
            'label' => "Status",
            'required' => true,
            'class' => ['form-control'],
            'option_values' => [
                1 => 'Publish',
                2 => 'Draft',
            ],
            'handler' => SelectField::class,
            'id' => 'status',
            'name' => 'status'
            // Add default settings from content type.
        ];
        $form['entity_name'] = [
            'type' => 'hidden',
            'default_value' => $this->content_type['machine_name'],
            'class' => [''],
            'id' => 'entity_name',
            'name' => 'entity_name'
        ];
        $form['owner'] = [
            'type' => 'reference',
            'label' => "Author",
            'required' => true,
            'class' => ['form-control'],
            'id' => 'owner',
            'name' => 'owner',
            'handler' => ReferenceField::class,
            'default_value' => CurrentUser::currentUser()->getUser()->getName() ?? User::load(1)->getName(),
            'reference' => [
                'type' => 'user',
                'reference_entity' => 'users',
            ]
        ];
        $form['submit'] = [
            'type' => 'submit',
            'default_value' => "Submit",
            'class' => ['btn btn-primary mt-5'],
            'id' => 'submit',
            'name' => 'submit'
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as &$field) {
            if ($field instanceof FieldBase && in_array($field->getType(),['fieldset','conditional','details'])) {
                $this->validate_recursive($field);
            }
            elseif ($field instanceof FieldBase && $field->getRequired() === 'required' && empty($field->getValue())) {
                $field->setError("{$field->getLabel()} is required");
                $this->validated = false;
            }
        }
    }

    private function validate_recursive(FieldBase &$field)
    {
        foreach ($field->getField()['inner_field'] as &$inner_field) {
            if ($inner_field instanceof FieldBase && in_array($inner_field->getType(),['fieldset','conditional','details'])) {
                return $this->validate_recursive($inner_field);
            }
            elseif ($inner_field instanceof FieldBase && $inner_field->getRequired() === 'required' && empty($field->getValue())) {
                $inner_field->setError("{$field->getName()} is required");
                $this->validated = false;
            }
        }
    }

    private function submit_recursive(&$field, array &$temp, Request $request, $data_all, $parent_key)
    {
        foreach ($field['inner_field'] as $k=>$inner_field) {
            if (in_array($inner_field['type'], ['fieldset', 'conditional', 'details'])) {
                return $this->submit_recursive($inner_field, $temp, $request, $data_all, $k);
            }

            elseif ($inner_field['type'] === 'file') {
                $files = $data_all[$parent_key][$k] ?? [];
                $files = is_string($files) ? json_decode($files,true) : $files;
                $processed_files = [];
                $file_fids = [];

                if (isset($files['name']) && is_array($files['name'])) {
                    $count = count($files['name']);
                    for ($i = 0; $i < $count; $i++) {
                        $processed_files[] = [
                            'name' => $files['name'][$i],
                            'full_path' => $files['full_path'][$i],
                            'size' => $files['size'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                        ];
                    }
                }
                elseif (isset($files['name']) && is_string($files['name'])) {
                    $processed_files[] = $files;
                }
                else {
                    $file_fids[] = $files;
                }


                foreach ($processed_files as $file) {

                    $form = new FormUpload();
                    $allowedExtensions = $inner_field['settings']['allowed_file_types'] ?? ['image/png', 'image/jpeg', 'image/gif'];
                    foreach ($allowedExtensions as $extension) {
                        $form->addAllowedExtension($extension);
                    }
                    $allowed_size = $inner_field['settings']['allowed_file_size'] ?? 1000000;
                    $form->addAllowedMaxSize($allowed_size);
                    $form->addFileObject($file);
                    $form->validate();
                    //TODO: get file location save.
                    $filename = "public://content";
                    if (!is_dir($filename)) {
                        @mkdir($filename);
                    }
                    $filename .= "/" . $file['name'];
                    $file = $form->moveFileUpload($filename);
                    $object = $file->getFileObject();
                    if ($object) {
                        $file = File::create(
                            [
                                'name' => $object['name'],
                                'size' => $object['size'],
                                'uri' => $object['file_path'],
                                'extension' => $object['extension'],
                                'mime_type' => $object['mime_type'],
                                'uid' => $data_all['uid']
                            ]
                        );
                        if ($file) {
                            $file_fids[] = $file->getFid();
                        }
                    }
                }
                $temp[$k] = $file_fids;
            }
            else {
                try {
                    $temp[$k] = $request->request->all($k);
                } catch (Throwable) {
                    $temp[$k] = $request->request->get($k);
                }
            }


        }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws Exception
     */
    public function submitForm(array $form): void
    {
        if ($this->validated) {

            $data_all = array_map(function ($value) {
                return $value->getValue();
            }, $form);

            $user = User::load($data_all['owner'][0] ?? 1);
            if ($user instanceof User) {
                $node_data = [
                    'title' => $data_all['title'] ?? null,
                    'status' => intval($data_all['status'] ?? 0) === 1 ? 1 : 0,
                    'uid' => $user->getUid(),
                    'lang' => $this->content_type['language'] ?? 'en',
                    'bundle' => $this->content_type['machine_name'] ?? '',
                ];
                $node_data = array_merge($data_all, $node_data);

                $request = Service::serviceManager()->request;

                $temp = [];
                foreach ($node_data as $key => $value) {
                    $field = $this->content_type["fields"][$key] ?? null;

                    if (isset($field) && $field['type'] === 'file') {
                        $files = $data_all[$key] ?? [];
                        $processed_files = [];
                        $files = is_string($files) ? json_decode($files,true) : $files;
                        $file_fids = [];

                        if ( !empty($files['name']) && is_array($files['name'])) {
                            $count = count($files['name']);
                            for ($i = 0; $i < $count; $i++) {
                                $processed_files[] = [
                                    'name' => $files['name'][$i],
                                    'full_path' => $files['full_path'][$i],
                                    'size' => $files['size'][$i],
                                    'type' => $files['type'][$i],
                                    'tmp_name' => $files['tmp_name'][$i],
                                    'error' => $files['error'][$i],
                                ];
                            }
                        }
                        elseif (!empty( $files['name']) && is_string($files['name'])) {
                            $processed_files[] = $files;
                        }
                        else {
                            $file_fids = $files;
                        }

                        foreach ($processed_files as $file) {

                            $form = new FormUpload();
                            $allowedExtensions = $field['settings']['allowed_file_types'] ?? ['image/png', 'image/jpeg', 'image/gif'];
                            foreach ($allowedExtensions as $extension) {
                                $form->addAllowedExtension($extension);
                            }
                            $allowed_size = $field['settings']['allowed_file_size'] ?? 1000000;
                            $form->addAllowedMaxSize($allowed_size);

                            $form->addFileObject($file);
                            $form->validate();
                            //TODO: get file location save.
                            $filename = "public://content";
                            if (!is_dir($filename)) {
                                @mkdir($filename);
                            }
                            $filename .= "/". $file['name'];
                            $file = $form->moveFileUpload($filename);
                            $object = $file->getFileObject();
                            if ($object) {
                                $file = File::create(
                                    [
                                        'name' => $object['name'],
                                        'size' => $object['size'],
                                        'uri' => $object['file_path'],
                                        'extension' => $object['extension'],
                                        'mime_type' => $object['mime_type'],
                                        'uid' => $node_data['uid']
                                    ]
                                );
                                if ($file) {
                                    $file_fids[] = $file->getFid();
                                }
                            }
                        }
                        $node_data[$key] = $file_fids;
                    }

                    elseif (isset($field) && in_array($field['type'], ['fieldset', 'details', 'conditional']))
                    {
                      $this->submit_recursive($field, $temp, $request, $node_data, $key);
                      unset($node_data[$key]);
                    }
                }
                $node_data = array_merge($node_data, $temp);

                // now insert in other tables.
                $node = Node::create($node_data);

                Messager::toast()->addMessage("Content of type {$this->content_type['name']} created");
                $redirect = new RedirectResponse('/admin/content');
                $redirect->setStatusCode(302);
                $redirect->send();
            }else {
                Messager::toast()->addWarning("Author name provided has not been found");
            }
        }
    }


}
