<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\uploads\FormUpload;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\user\entity\User;
use Simp\Default\ConditionalField;
use Simp\Default\FieldSetField;
use Simp\Default\FileField;
use Simp\Default\SelectField;
use Simp\Default\TextAreaField;
use Simp\FormBuilder\FormBase;
use Simp\Translate\lang\LanguageManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class ProfileEditForm extends FormBase
{
    protected bool $validated = false;
    public function getFormId(): string
    {
        return 'profile_edit';
    }

    public function buildForm(array &$form): array
    {
        $request = Service::serviceManager()->request;
        $user = User::load($request->get('uid'));
        if ($profile = $user->getProfile()) {
            $form['first_name'] = [
                'label' => 'First Name',
                'name' => 'first_name',
                'type' => 'text',
                'id' => 'first_name',
                'class' => ['form-control'],
                'default_value' => $profile->getFirstName(),
            ];
            $form['last_name'] = [
                'label' => 'Last Name',
                'name' => 'last_name',
                'type' => 'text',
                'id' => 'last_name',
                'class' => ['form-control'],
                'default_value' => $profile->getLastName(),
            ];
            $form['profile_image'] = [
                'label' => 'Profile Image',
                'name' => 'profile_image',
                'type' => 'file',
                'id' => 'profile_image',
                'class' => ['form-control'],
                'handler' => FileField::class,
            ];
            $checked = [];
            if ($profile->isTranslationEnabled()) {
                $checked['checked'] = $checked;
            }
            $form['translations'] = [
                'label' => 'Translations',
                'name' => 'translations',
                'type' => 'conditional',
                'id' => 'translations',
                'class' => ['form-group'],
                'handler' => ConditionalField::class,
                'inner_field' => [
                    'enable_translation' => [
                        'label' => 'Enable Translation',
                        'name' => 'enable_translation',
                        'type' => 'select',
                        'id' => 'enable_translation',
                        'class' => ['form-check'],
                        'default_value' => $profile->isTranslationEnabled() ? 'yes' : 'no',
                        'option_values' => [
                            'yes' => 'Yes',
                            'no' => 'No',
                        ],
                        'handler' => SelectField::class,
                    ],
                    'language' => [
                        'label' => 'Language',
                        'name' => 'language',
                        'type' => 'select',
                        'id' => 'language',
                        'class' => ['form-control'],
                        'option_values' => LanguageManager::manager()->getLanguages(),
                        'default_value' => 'ny',
                        'handler' => SelectField::class,
                    ],
                ],
                'conditions' => [
                    'enable_translation' => [
                        'event' => 'change',
                        'receiver_field' => 'language'
                    ]
                ],
                'description' => 'this site uses "english" as default language.'

            ];
            $form['description'] = [
                'label' => 'Description',
                'name' => 'description',
                'type' => 'textarea',
                'id' => 'description',
                'class' => ['form-control'],
                'handler' => TextareaField::class,
                'default_value' => $profile->getDescription(),
            ];
            $form['submit'] = [
                'type' => 'submit',
                'name' => 'submit',
                'id' => 'submit',
                'class' => ['btn btn-primary'],
                'default_value' => 'Submit',
            ];
            return $form;
        }
        return $form;
    }

    public function validateForm(array $form): void
    {

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
        $request = Service::serviceManager()->request;
        $user = User::load($request->get('uid'));
        $profile = $user->getProfile();
        //TODO: upload image here if exist.
        $image = $form['profile_image']->getValue();
        if (!empty($image) && !empty($image['name'])) {

            if (!is_dir("public://profiles")) {
                mkdir("public://profiles");
            }
            $image = FormUpload::uploadFormImage($image, "public://profiles/profile_{$image['name']}");
            $image = $image->getFileObject();
            if ($image){
                $file = File::create([
                    'name' => $image['name'],
                    'size' => $image['size'],
                    'uri' => $image['file_path'],
                    'extension' => $image['extension'],
                    'mime_type' => $image['mime_type'],
                    'uid' => $request->get('uid')
                ]);
                $fid = $profile->getProfileImage();
                if ($fid){
                    $file_old = File::load($fid);
                    $file_old->delete();
                }
                if ($file instanceof File) {
                    $profile->setProfileImage($file->getFid());
                }
            }

        }
        if (!empty($form['first_name']->getValue())) {
            $profile->setFirstName( $form['first_name']->getValue() );
        }
        if (!empty($form['last_name']->getValue())) {
           $profile->setLastName( $data['last_name'] = $form['last_name']->getValue() );
        }
        if (!empty($form['description']->getValue())) {
            $profile->setDescription( $form['description']->getValue() );
        }

        if (!empty($form['translations']?->getValue()['enable_translation'])) {
            $profile->setTranslation($form['translations']?->getValue()['enable_translation'] == 'yes' ? 1 : 0);
        }
        if (!empty($form['translations']?->getValue()['language'])) {
            $profile->setTranslationCode( $form['translations']?->getValue()['language']);
        }

        if ($profile->update()) {
            $redirect = new RedirectResponse('/user/'.$user->getUid());
            Messager::toast()->addMessage("{$user->getName()} profile has been updated.");
            $redirect->send();
        }
        else {
            Messager::toast()->addError("{$user->getName()} profile could not be updated.");
        }
    }
}