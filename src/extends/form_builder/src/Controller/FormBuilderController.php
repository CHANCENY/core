<?php

namespace Simp\Core\extends\form_builder\src\Controller;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\extends\form_builder\src\Form\FormConfigureEditForm;
use Simp\Core\extends\form_builder\src\Form\SubmissionEditFormHandler;
use Simp\Core\extends\form_builder\src\Plugin\FormConfigManager;
use Simp\Core\extends\form_builder\src\Plugin\FormSettings;
use Simp\Core\extends\form_builder\src\Plugin\Submission;
use Simp\Core\lib\routes\Route;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\messager\Messager;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class FormBuilderController
{
    public function dashboard(...$args): Response
    {
        return new Response(View::view('default.view.form_builder.dashboard'));
    }

    public function save(...$args): JsonResponse
    {
        extract($args);
        $json_form = json_decode($request->getContent(), true);

        if (!empty($json_form['name']) && !empty($json_form['fields'])) {
            $config = [
                'title' => $json_form['name'],
                'name' => $json_form['name'],
                'attributes' => [
                    'id' => $json_form['name'],
                    'class' => 'form-builder-form',
                    'method' => $json_form['method'] ?? 'POST',
                    'action' => $json_form['action'] ?? '',
                    'enctype' => $json_form['enctype'] ?? 'multipart/form-data',
                    'accept-charset' => $json_form['accept_charset'] ?? 'UTF-8',
                    'is_silent' => $json_form['is_silent'] ?? false,
                ],
                'fields' => $json_form['fields'],
            ];

            if (FormConfigManager::factory()->createForm($json_form['name'], $config)) {
                return new JsonResponse(['success' => true, 'message' => 'Form saved successfully']);
            }
        }
        return new JsonResponse(['success' => true, 'message' => 'coming soon'], 404);
    }

    public function list(...$args)
    {
        $forms = FormConfigManager::factory()->getForms();
        return new Response(View::view('default.view.form_builder.list',['forms'=>$forms]));
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete(...$args)
    {
        extract($args);
        $form_name = $request->get('name');
        if (FormConfigManager::factory()->deleteForm($form_name)) {
            Messager::toast()->addMessage("Form '$form_name' has been deleted");
            return new RedirectResponse(Route::fromRouteName('form_builder.list')->route_path);
        }
        Messager::toast()->addError("Form '$form_name' not found");
        return new RedirectResponse(Route::fromRouteName('form_builder.list')->route_path);
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function edit(...$args)
    {
        extract($args);
        $form_name = $request->get('name');
        $form = FormConfigManager::factory()->getForm($form_name);

        $form_builder = new FormBuilder(new FormConfigureEditForm($form));
        $form_builder->getFormBase()->setFormMethod('POST');

        return new Response(View::view('default.view.form_builder.config.edit', ['form' => $form_builder]));
    }

    /**
     * @param ...$args
     * @return Response
     * @throws LoaderError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function form_settings(...$args): Response
    {
        extract($args);
        $form_name = $request->get('name');
        $form = FormConfigManager::factory()->getForm($form_name);
        $settings = FormSettings::factory($form_name);

        if ($request->getMethod() === 'POST') {

            $route = Route::fromRouteUrl($request->request->get('slug'));

            if (!empty($request->request->get('title')) && !empty($request->request->get('status')) && !empty($request->request->get('require_login'))
                 && !empty($request->request->get('limit')) && !empty($request->request->get('confirmation')) && !empty($request->request->get('embedded'))) {

                if (!empty($settings->getSlug()) && $settings->update(...$request->request->all())) {
                    Messager::toast()->addMessage("Form settings saved successfully");
                    return new RedirectResponse(Route::fromRouteName('form_builder.list')->route_path);
                }
                elseif (empty($route) && $settings->create(...$request->request->all())) {
                    Messager::toast()->addMessage("Form settings saved successfully");
                    return new RedirectResponse(Route::fromRouteName('form_builder.list')->route_path);
                }
            }
            else {
                Messager::toast()->addError("Form settings not saved, might be invalid slug or already in used");
            }
        }
        return new Response(View::view('default.view.form_builder.form_settings',['title'=>$form['title'],'form'=>$form, 'settings'=>$settings]));
    }

    public function form_submission(...$args): Response
    {
        extract($args);
        $form_name = $request->get('name');
        $form = FormConfigManager::factory()->getForm($form_name);
        $settings = FormSettings::factory($form_name);

        $submissions = Submission::loadByFormName($form_name);
        $submissions = Submission::loadMultiple($submissions->getSids());

        return new Response(View::view('default.view.form_builder.submission',['title' => $settings->getTitle(), 'form' => $form,'submissions'=>$submissions, 'form_name'=> $form_name]));
    }

    public function form_submission_delete(...$args): RedirectResponse {
        extract($args);
        $sid = $request->get('sid');
        $form_name = $request->get('name');
        $submission = Submission::load($sid);
        if ($submission->delete()) {
            Messager::toast()->addMessage("Webform submission deleted successfully");;
        }
        else {
            Messager::toast()->addError("Webform submission not deleted");
        }
        return new RedirectResponse("/admin/form-builder/{$form_name}/submission");
    }

    public function form_submission_view(...$args): Response {
        extract($args);
        $sid = $request->get('sid');
        $form_name = $request->get('name');
        $submission = Submission::load($sid);
        $fields = FormConfigManager::factory()->getForm($form_name)['fields'];
        //dump($submission);
        return new Response(View::view('default.view.form_builder.submission_view',['submission'=>$submission, 'fields'=>$fields, 'form_name'=>$form_name]));
    }

    public function form_submission_edit(...$args)
    {
        extract($args);
        $sid = $request->get('sid');
        $form_name = $request->get('name');
        $submission = Submission::load($sid);
        $form_config = FormConfigManager::factory()->getForm($form_name);
        $form_settings = FormSettings::factory($form_name);

        if (!empty($form_settings) && !empty($form_config)) {

            $embedded_html = $form_settings->getEmbedded();

            $form_base = new FormBuilder(new SubmissionEditFormHandler([
                'form_id' => $form_config['attributes']['id'],
                'fields' => $form_config['fields'],
                'submission' => Submission::load($sid),
            ]));
            $form_base->getFormBase()->setFormMethod($form_config['attributes']['method']);
            $form_base->getFormBase()->setFormAction($form_config['attributes']['action']);
            $form_base->getFormBase()->setFormEnctype($form_config['attributes']['enctype']);
            $form_base->getFormBase()->setFormAcceptCharset($form_config['attributes']['accept-charset']);
            $form_base->getFormBase()->isFormSilent($form_config['attributes']['is_silent']);

            $content = str_replace('[__form__]',$form_base, $embedded_html);
            return new Response(View::view('default.view.form_builder.submission.handler',
                [
                    'embedded' => $content,
                    'page_title' => $form_settings->getTitle(),
                ]
            )
            );
        }
        return new Response("Form submission");
    }

}