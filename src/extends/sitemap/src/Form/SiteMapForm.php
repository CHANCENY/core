<?php

namespace Simp\Core\extends\sitemap\src\Form;

use Google\Service\Compute\Router;
use Simp\Core\components\basic_fields\SelectField;
use Simp\Core\components\markup_field\MarkUpField;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\taxonomy\VocabularyManager;
use Simp\Fields\FieldBase;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SiteMapForm extends FormBase
{

    protected bool $validated = true;

    public function getFormId(): string
    {
        return "sitemap";
    }

    public function buildForm(array $form): array
    {

        $settings = ConfigManager::config()->getConfigFile('sitemap');
        $form = parent::buildForm($form);
        $form['markup'] = [
            'type' => 'markup',
            'markup' => "<p>Configure basic sitemap data eg content type, vocabulary terms</p>",
            'name' => 'markup',
            'handler' => MarkUpField::class
        ];

        $types = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        $terms = VocabularyManager::factory()->getVocabularies();
        $routes = Route::getRoutes();

        $routes_list = [];

        /**@var Route $route**/
        foreach ($routes as $route) {
            $routes_list[$route->route_id] = $route->route_title;
        }

        $types = array_map(function ($type) {
            return $type['name'] ?? $type['title'] ?? null;
        }, $types);
        $terms = array_map(function ($term) {
            return $term['label'];
        }, $terms);

        $form['content_type'] = [
            'type' => 'select',
            'label' => 'Content Type',
            'id' => 'content_type',
            'class' => ['form-control'],
            'description' => 'Choose a content types',
            'limit' => 50,
            'option_values' => [
                ...$types,
            ],
            'required' => false,
            'handler' => SelectField::class,
            'name' => 'content_type',
            'default_value' => $settings?->get('content_type',[])
        ];

        $form['terms'] = [
            'type' => 'select',
            'label' => 'Term',
            'id' => 'terms',
            'class' => ['form-control'],
            'description' => 'Choose a term',
            'limit' => 50,
            'option_values' => [
                ...$terms,
            ],
            'required' => false,
            'handler' => SelectField::class,
            'name' => 'terms',
            'default_value' => $settings?->get('terms',[])
        ];

        $form['ignore_routes'] = [
            'type' => 'select',
            'label' => 'Ignore Routes',
            'id' => 'ignore_routes',
            'class' => ['form-control'],
            'description' => 'Choose a route',
            'limit' => 700,
            'option_values' => $routes_list,
            'handler' => SelectField::class,
            'name' => 'ignore_routes',
            'default_value' => $settings?->get('ignore_routes',[])
        ];

        $form['submit'] = [
            'type' => 'submit',
            'class' => ['btn btn-primary'],
            'id' => 'submit',
            'name' => 'submit',
            'default_value' => 'Save',
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field instanceof FieldBase && $field->getRequired() === 'required') {
                $field->setError("field is required");
            }
        }
    }

    public function submitForm(array $form): void
    {
        $data = array_map(function ($item) {
            return $item->getValue();
        }, $form);

        unset($data['markup']);
        unset($data['submit']);

        ConfigManager::config()->addConfigFile('sitemap', $data);
        $redirect = new RedirectResponse(Route::url('sitemap.xml.dashboard'));
        $redirect->setStatusCode(302);
        $redirect->send();

    }

}