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

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);
        $request = Service::serviceManager()->request;
        $user = User::load($request->get('uid'));
        if ($profile = $user->getProfile()) {

            $form['first_name']['default_value'] = $profile->getFirstName();
            $form['last_name']['default_value'] = $profile->getLastName();
            $checked = [];
            if ($profile->isTranslationEnabled()) {
                $checked['checked'] = $checked;
            }
            $form['translations']['inner_field']['enable_translation']['default_value'] =  $profile->isTranslationEnabled() ? 'yes' : 'no';
            $form['translations']['inner_field']['language']['option_values'] = LanguageManager::manager()->getLanguages();
            $form['translations']['inner_field']['language']['default_value'] =  $profile->getTranslationCode();
            $form['description']['default_value'] = $profile->getDescription();

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
    public function submitForm(array $form): void
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