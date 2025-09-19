<?php

namespace Simp\Core\extends\wiki\src\Form;

use DI\DependencyException;
use DI\NotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\basic_fields\FieldSetField;
use Simp\Core\components\basic_fields\RadioField;
use Simp\Core\components\basic_fields\TextAreaField;
use Simp\Core\components\markup_field\MarkUpField;
use Simp\Core\components\reference_field\ReferenceField;
use Simp\Core\extends\wiki\src\Entity\Wiki;
use Simp\Core\extends\wiki\src\enum\WikiStatusEnum;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class WikiCreateForm extends FormBase
{

    protected bool $validated = true;

    public function getFormId(): string
    {
        return 'wiki_create';
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);

        $form['wiki_create_form'] = [
            'type' => 'fieldset',
            'label' => 'Wiki Creation Form',
            'description' => 'This is a wiki creation form',
            'name' => 'wiki_create_form',
            'id' => 'wiki_create_form',
            'class' => [],
            'handler' => FieldSetField::class,
        ];

        $form['wiki_create_form']['inner_field'] = [
            'markup' => [
                'type' => 'markup',
                'markup' =>  '<p>You can create a new wiki tag using this form: <a href="' . Route::url('wiki.tag.create') . '">Create Wiki Tag</a></p>',
                'name' => 'markup',
                'id' => 'markup',
                'class' => [],
                'handler' => MarkUpField::class
            ],
            'wiki_title' => [
                'type' => 'text',
                'label' => 'Wiki Title',
                'name' => 'wiki_title',
                'id' => 'wiki_title',
                'class' => [],
                'description' => 'This is a wiki title and is required',
                'required' => true,
                'limit' => 1
            ],
            'wiki_tag' => [
                'type' => 'reference',
                'label' => 'Wiki Tag',
                'name' => 'wiki_tag',
                'id' => 'wiki_tag',
                'class' => [],
                'description' => 'This is a wiki tag and is required',
                'required' => true,
                'handler' => ReferenceField::class,
                'limit' => 10,
                'reference' => [
                    'type' => 'term',
                    'reference_entity' => 'wiki'
                ]
            ],
            'wiki_content' => [
                'type' => 'textarea',
                'label' => 'Wiki Content',
                'name' => 'wiki_content',
                'id' => 'wiki_content',
                'class' => ['editor'],
                'description' => 'This is a wiki content and is required',
                'required' => true,
                'limit' => 1,
                'handler' => TextAreaField::class
            ],
            'wiki_status' => [
                'type' => 'radio',
                'label' => 'Wiki Status',
                'name' => 'wiki_status',
                'id' => 'wiki_status',
                'class' => [],
                'description' => 'This is a wiki status and is required',
                'required' => true,
                'limit' => 1,
                'radios' => [
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived'
                ],
                'handler' => RadioField::class,
                'default_value' => 'published'
            ]
        ];
        $form['submit'] = [
            'type' => 'submit',
            'label' => '⚠️ Once created, this content cannot be edited directly.  
        Any changes will be recorded in the revision history, keeping this version intact.',
            'name' => 'submit',
            'id' => 'submit',
            'class' => [],
            'default_value' => 'Submit'
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        $wiki_values = array_map(fn($value) => $value->getValue(), $form);
        $wiki_values = $wiki_values['wiki_create_form'];

        if (empty($wiki_values['wiki_title'])) {
            $this->validated = false;
        }

        if (empty($wiki_values['wiki_tag'])) {
            $this->validated = false;
        }

        if (empty($wiki_values['wiki_content'])) {
            $this->validated = false;
        }

        if (empty($wiki_values['wiki_status'])) {
            $this->validated = false;
        }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws DependencyException
     * @throws NotFoundException
     * @throws \DateMalformedStringException
     */
    public function submitForm(array $form): void
    {
        $wiki_values = array_map(fn($value) => $value->getValue(), $form);
        $wiki_values = $wiki_values['wiki_create_form'];

        if ($this->validated) {

            $status = WikiStatusEnum::tryFrom(strtolower((string) $wiki_values['wiki_status']));

            if (empty($status)) {
                $status = WikiStatusEnum::DRAFT;
            }

            $wiki = Wiki::create([
                'title' => $wiki_values['wiki_title'],
                'content' => $wiki_values['wiki_content'],
                'status' => $status,
                'tags' => $wiki_values['wiki_tag'],
                'authors' => [
                    CurrentUser::currentUser()->getUser()->id()
                ]
            ]);

            $wiki->enforceNew();
            $wiki->save();

            $wiki = Wiki::load($wiki->id());

            $redirect_url = new RedirectResponse(Route::url('wiki.entry', ['slug' => $wiki->getSlug()]));
            $redirect_url->send();
        }
    }
}