<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\search\SearchManager;
use Simp\Default\FieldSetField;
use Simp\Default\SelectField;
use Simp\Fields\FieldBase;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Simp\Core\modules\services\Service;

class SearchFormConfiguration extends FormBase
{

    protected bool $validated = true;

    public function getFormId(): string
    {
        return 'search_form_configuration';
    }

    public function buildForm(array &$form): array
    {
        $key = Service::serviceManager()->request->get('key','');
        $search = SearchManager::searchManager()->getSetting($key);
        $form['search_wrapper'] = array(
            'type' => 'fieldset',
            'name' => 'search_wrapper',
            'id' => 'search_wrapper',
            'class' => ['form-wrapper'],
            'handler' => FieldSetField::class,
            'label' => '',
            'inner_field' => array(
                'search_name' =>  [
                    'type' => 'text',
                    'name' => 'search_name',
                    'label' => 'Search Name',
                    'id' => 'search_name',
                    'class' => ['form-control'],
                    'required' => true,
                    'description' => 'this name will be used to create search identifiable key for URL pattern key "type"',
                    'default_value' => $search['name'] ?? null,
                ],
                'search_type' => [
                    'type' => 'select',
                    'name' => 'search_type',
                    'label' => 'Search Type',
                    'id' => 'search_type',
                    'class' => ['form-control'],
                    'required' => true,
                    'option_values' => [
                        'content_type' => 'Content Type',
                        'user_type' => 'User Type',
                    ],
                    'handler' => SelectField::class,
                    'default_value' => $search['type'] ?? null,
                ],
                'search_result_template' => [
                    'type' => 'text',
                    'name' => 'search_result_template',
                    'label' => 'Search Result Template',
                    'id' => 'search_result_template',
                    'class' => ['form-control'],
                    'required' => true,
                    'default_value' => $search['template'] ?? null,
                ]
            )
        );

        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'id' => 'submit',
            'default_value' => 'Submit',
            'class' => ['btn btn-primary'],
        ];
        return $form;
    }

    protected function inner_validate(FieldBase &$field): void
    {
        if ($field->getRequired() === 'required' && empty($field->getValue())) {
            $field->setError("this field is required");
            $this->validated = false;
        }
    }

    public function validateForm(array $form): void
    {
        /**@var FieldBase $field**/
        $field = &$form['search_wrapper'];
        foreach ($field->getField() as &$details) {
            if (!empty($details['inner_field'])) {

                foreach ($details['inner_field'] as &$inner_field) {
                    $this->inner_validate($inner_field);
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
     */
    public function submitForm(array &$form): void
    {
        if ($this->validated) {
            $search_key = Service::serviceManager()->request->get('key');

            $data = array_map(fn($field)=> $field->getValue(), $form);
            $data = $data['search_wrapper'];

            if (!empty($data['search_name'])) {
                $name = strtolower(str_replace(' ', '-', $data['search_name']));
                $name = !empty($search_key) ? $search_key : $name;
                $new_data['name'] = $data['search_name'];

                if (!empty($data['search_type'])) {
                    $new_data['type'] = $data['search_type'];
                }
                if (!empty($data['search_result_template'])) {
                    $new_data['template'] = $data['search_result_template'];
                }

                if(!empty($search_key)){
                    $setting = SearchManager::searchManager()->getSetting($search_key);
                    $new_data = array_merge($setting, $new_data);
                }
                if (SearchManager::searchManager()->addSetting($name, $new_data)) {
                    Messager::toast()->addMessage("Added search setting");
                    (new RedirectResponse('/admin/search/settings',302))->send();
                }else {
                    Messager::toast()->addError("Failed to add search setting");
                }
            }
        }
    }
}