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

    public function buildForm(array $form): array
    {
        $term = Term::factory()->getTerm(Service::get('request')->get('tid',0));
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
            'default_value' => $term === null || $term === [] ? null : $term['label'],
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
    public function submitForm(array $form): void
    {
        $vid = Service::get('request')->get('name');
        $tid = Service::get('request')->get('tid');

        if (!empty($vid) && empty($tid)) {
            if (Term::factory()->create($vid, $form['title']->getValue())) {
                Messager::toast()->addMessage(sprintf("Term '%s' has been added", $form['title']->getValue()));
                $redirect = new RedirectResponse('/admin/structure/taxonomy/'.$vid.'/terms');
                $redirect->send();
                return;
            }
        } elseif (Term::factory()->update($tid, $form['title']->getValue())) {
            Messager::toast()->addMessage(sprintf("Term '%s' has been updated", $form['title']->getValue()));
            $redirect = new RedirectResponse('/admin/structure/taxonomy/'.$vid.'/terms');
            $redirect->send();
            return;
        }

    }
}