<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\structures\taxonomy\Term;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TermAddForm extends FormBase
{

    public function getFormId(): string
    {
        return 'termAddForm';
    }

    public function buildForm(array &$form): array
    {
        $term = Term::factory()->getTerm(Service::serviceManager()->request->get('tid',0));
        $form['title'] = [
            'type' => 'text',
            'label' => 'Title',
            'id' => 'title',
            'required' => true,
            'options' => [
                'autofocus' => 'autofocus',
            ],
            'class' => [],
            'name' => 'title',
            'default_value' => !empty($term) ? $term['label'] : null,
        ];
        $form['submit'] = [
            'type' => 'submit',
            'default_value' => 'Save',
            'id' => 'submit',
            'name' => 'submit',
            'class' => ['btn' , 'btn-primary'],
        ];
       return $form;
    }

    public function validateForm(array $form): void
    {
        if ($form['title']->getRequired() === 'required' && empty($form['title']->getValue())) {
            $form['title']->addError("Title is required");
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
        $vid = Service::serviceManager()->request->get('name');
        $tid = Service::serviceManager()->request->get('tid');

        if (!empty($vid) && empty($tid)) {
            if (Term::factory()->create($vid, $form['title']->getValue())) {
                Messager::toast()->addMessage("Term '{$form['title']->getValue()}' has been added");
                $redirect = new RedirectResponse('/admin/structure/taxonomy/'.$vid.'/terms');
                $redirect->send();
                return;
            }
        }

        else {

            if (Term::factory()->update($tid, $form['title']->getValue())) {
                Messager::toast()->addMessage("Term '{$form['title']->getValue()}' has been updated");
                $redirect = new RedirectResponse('/admin/structure/taxonomy/'.$vid.'/terms');
                $redirect->send();
                return;
            }

        }

    }
}