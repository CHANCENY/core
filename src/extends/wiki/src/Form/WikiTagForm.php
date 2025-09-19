<?php

namespace Simp\Core\extends\wiki\src\Form;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\basic_fields\FieldSetField;
use Simp\Core\components\markup_field\MarkUpField;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\taxonomy\Term;
use Simp\FormBuilder\FormBase;

class WikiTagForm extends FormBase
{

    protected bool $validated = true;

    public function getFormId(): string
    {
        return 'wiki_tag';
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);

        $form['wiki_tag_form'] = [
            'type' => 'fieldset',
            'label' => 'Wiki Tag Form',
            'description' => 'This is a wiki tag form',
            'name' => 'wiki_tag_form',
            'id' => 'wiki_tag_form',
            'class' => [],
            'handler' => FieldSetField::class
        ];
        $form['wiki_tag_form']['inner_field'] = [
            'markup' => [
                'type' => 'markup',
                'name' => 'markup',
                'id' => 'markup',
                'markup' => "<p>Create Wiki tag you can use this tag on Wiki Tag field of <a href='".Route::url('wiki.create')."'>Wiki creation form</a>",
                'handler' => MarkUpField::class
            ],
            'wiki_tag' => [
                'type' => 'text',
                'label' => 'Wiki Tag',
                'name' => 'wiki_tag',
                'id' => 'wiki_tag',
                'class' => [],
                'description' => 'This is a wiki tag and is required',
                'required' => true,
                'limit' => 1
            ]
        ];

        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'id' => 'submit',
            'class' => [],
            'default_value' => 'Submit'
        ];

        return $form;
    }


    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validateForm(array $form): void
    {
        $values = array_map(fn($value) => $value->getValue(), $form);

        if (empty($values['wiki_tag_form']['wiki_tag'])) {
            $this->validated = false;
            Messager::toast()->addMessage("Wiki tag is required");
        }

    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function submitForm(array $form): void
    {
        if ($this->validated) {

            $function = function(string $name): ?string {
                $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
                $name = preg_replace('/\s+/', '_', (string) $name);
                return preg_replace('/_+/', '_', (string) $name);
            };

            $values = array_map(fn($value) => $value->getValue(), $form);

            $label = $function($values['wiki_tag_form']['wiki_tag']);

            $term = Term::factory()->get($label);

            if ($term === []) {
                $term = Term::factory()->create('wiki', $values['wiki_tag_form']['wiki_tag']);
                if ($term) {
                    Messager::toast()->addMessage("Wiki tag created successfully");;
                }
                else {
                    Messager::toast()->addMessage("Failed to create wiki tag");
                }
            }
            else {
                Messager::toast()->addMessage("Wiki tag already exists");
            }

        }
    }
}