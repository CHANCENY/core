<?php

namespace Simp\Core\extends\auto_path\src\controller;

use Simp\Core\extends\auto_path\src\form\CreateAutoPathForm;
use Simp\Core\extends\auto_path\src\path\AutoPathAlias;
use Simp\Core\lib\themes\View;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class AutoPathController
{
    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function auto_path_create(...$args): Response {
        $form_base = new FormBuilder(new CreateAutoPathForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.auto_path_create',['_form'=>$form_base]), Response::HTTP_OK);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function auto_path_list(...$args): Response {
        $aliases = AutoPathAlias::factory()->listAliases();
        return new Response(View::view('default.view.auto_path_list',['auto_paths'=>$aliases]));
    }

    public function auto_path_delete(...$args): Response|RedirectResponse {

        extract($args);
        $id = $request->get('id', 0);
        if (!empty($id)) {
            AutoPathAlias::factory()->deleteAlias($id);
        }
        return new RedirectResponse('/admin/auto-path/list');
    }
}