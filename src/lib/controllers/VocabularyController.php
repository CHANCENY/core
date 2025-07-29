<?php

namespace Simp\Core\lib\controllers;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\forms\TermAddForm;
use Simp\Core\lib\forms\VocabularyForm;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\structures\taxonomy\Term;
use Simp\Core\modules\structures\taxonomy\VocabularyManager;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class VocabularyController
{

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function manage(...$args): Response
    {
        extract($args);
        $vocabularies = VocabularyManager::factory()->getVocabularies();
        return new Response(View::view('default.view.manage',['list'=>$vocabularies]), Response::HTTP_OK);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function add(...$args): Response
    {
        $form = new FormBuilder(new VocabularyForm());
        $form->getFormBase()->setFormEnctype('multipart/form-data');
        $form->getFormBase()->setFormMethod('POST');
        return new Response(View::view('default.view.add_vocabulary',['_form'=>$form]), Response::HTTP_OK);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function edit(...$args): Response {
        return $this->add(...$args);
    }

    public function delete(...$args): Response {
        extract($args);
        $name = $request->get("name");
        if (!empty($name)) {
            if (VocabularyManager::factory()->removeVocabulary($name)) {
                return new RedirectResponse("/admin/structure/taxonomy");
            }
        }
        return new RedirectResponse("/admin/structure/taxonomy");
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function term_list(...$args): Response
    {
        extract($args);
        $terms = Term::factory()->getTermByVid($request->get('name',''));
        return new Response(View::view('default.view.term_list',['terms'=>$terms]), Response::HTTP_OK);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function term_add(...$args): Response
    {
        extract($args);

        $form = new FormBuilder(new TermAddForm());
        $form->getFormBase()->setFormEnctype('multipart/form-data');
        $form->getFormBase()->setFormMethod('POST');
        return new Response(View::view('default.view.term_add',['_form'=>$form, '_title'=> (!empty($_title)) ? $_title : 'New Term']), Response::HTTP_OK);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function term_edit(...$args): Response
    {
        $args['_title'] = 'Edit Term';
        return $this->term_add(...$args);
    }

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function term_delete(...$args): Response {
        extract($args);
        $name = $request->get("name");
        $tid = $request->get("tid");
        if (!empty($name) && !empty($tid)) {
            if (Term::factory()->delete($tid)) {
                Messager::toast()->addMessage('Term '.$name.' has been deleted');
                return new RedirectResponse("/admin/structure/taxonomy/$name/terms");
            }
        }
        return new RedirectResponse("/admin/structure/taxonomy/$name/terms");
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function term_view(...$args): Response
    {
        extract($args);
        $name = $request->get("name");
        $nodes = [];
        if (!empty($name)) {
            $terms = Term::factory()->get($name);
            $terms = array_column($terms,'id');

            if (!empty($terms)) {
                $nodes = Node::referenceEntities($terms);
            }

        }
        return new Response(View::view('default.view.term_view',['nodes'=>$nodes]), Response::HTTP_OK);
    }
}