<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\structures\taxonomy\VocabularyManager;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class VocabularyForm extends FormBase
{

    protected bool $validated = true;

    public function getFormId(): string
    {
        return 'vocabulary';
    }

    public function buildForm(array &$form): array
    {
        $taxonomy = [];
        $name = Service::serviceManager()->request->get('name');
        if($name) {
            $taxonomy = VocabularyManager::factory()->getVocabulary($name)['label'] ?? Service::serviceManager()->request->get('name');
        }
        $form['title'] = [
            'type' => 'text',
            'label' => 'Title',
            'name' => 'title',
            'id' => 'title',
            'class' =>[],
            'required' => true,
            'default_value' => Service::serviceManager()->request->get('name', null)
        ];
        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'id' => 'submit',
            'class' => ['btn', 'btn-primary'],
            'default_value' => 'Submit',
        ];
       return $form;
    }

    public function validateForm(array $form): void
    {
        if ($form['title']->getRequired() === 'required' &&  empty($form['title']->getValue())) {
            $form['title']->setError('this field is required.');
            $this->validated = false;
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

            $name = Service::serviceManager()->request->get('name');

            if (empty($name)) {

                $title = $form['title']->getValue();
                if (VocabularyManager::factory()->addVocabulary($title)) {
                    Messager::toast()->addMessage('Vocabulary created.');
                    $redirect = new RedirectResponse('/admin/structure/taxonomy');
                    $redirect->setStatusCode(302);
                    $redirect->send();
                    return;

                }
                else{
                    Messager::toast()->addError('Vocabulary could not be created.');
                }

            }
            else {
                $title = $form['title']->getValue();
                if (VocabularyManager::factory()->updateVocabulary($name, $title)) {
                    Messager::toast()->addMessage('Vocabulary updated.');
                    $redirect = new RedirectResponse('/admin/structure/taxonomy');
                    $redirect->setStatusCode(302);
                    $redirect->send();
                    return;

                }
                else{
                    Messager::toast()->addError('Vocabulary could not be updated.');
                }
            }

        }
    }
}