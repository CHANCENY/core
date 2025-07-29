<?php

namespace Simp\Core\lib\controllers;

use Simp\Core\lib\forms\DatabaseForm;
use Simp\Core\lib\themes\View;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\Response;

class DatabaseController
{
    public function database_form(...$args): Response
    {
        $form_base = new FormBuilder(new DatabaseForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.database_form',['_form'=>$form_base]), Response::HTTP_OK);
    }
}