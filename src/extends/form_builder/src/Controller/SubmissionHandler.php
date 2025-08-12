<?php

namespace Simp\Core\extends\form_builder\src\Controller;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\form\FormDefinitionBuilder;
use Simp\Core\extends\form_builder\src\Form\SubmissionFormHandler;
use Simp\Core\extends\form_builder\src\Plugin\FormConfigManager;
use Simp\Core\extends\form_builder\src\Plugin\FormSettings;
use Simp\Core\lib\themes\View;
use Simp\Default\BasicField;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SubmissionHandler
{
    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function formSubmission(...$args)
    {
        extract($args);

        /**@var \Simp\Core\lib\routes\Route $route**/
        $route = $options['route'] ?? null;

        $form_settings = null;
        $form_config = [];

        if ($route) {
            $form_name = $route->options['form_name'];

            // Load form settings
            if (!empty($form_name)) {

                $settings = FormSettings::factory($form_name);
                if ($settings->isFormActive()) {
                    $form_settings = $settings;
                }

                $form_config = FormConfigManager::factory()->getForm($form_name);

            }
        }

        if (!empty($form_settings) && !empty($form_config)) {

            $embedded_html = $form_settings->getEmbedded();

            $form_base = new FormBuilder(new SubmissionFormHandler([
                'form_id' => $form_config['attributes']['id'],
                'fields' => $form_config['fields'],
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