<?php

namespace Simp\Core\extends\form_builder\src\Field;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\components\form\FormDefinitionBuilder;
use Simp\Core\extends\form_builder\src\Form\SubmissionFormHandler;
use Simp\Core\extends\form_builder\src\Plugin\FormConfigManager;
use Simp\Core\extends\form_builder\src\Plugin\FormSettings;
use Simp\Core\extends\form_builder\src\Plugin\Submission;
use Simp\Core\extends\page_builder\src\Plugin\Page;
use Simp\Core\lib\routes\Route;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Default\BasicField;
use Simp\Fields\FieldBase;
use Simp\Fields\FieldRequiredException;
use Simp\Fields\FieldTypeSupportException;
use Simp\FormBuilder\FormBuilder;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class FormBuilderField extends FieldBase
{
    private array $field;
    private array $submission;
    protected string $validation_message;
    private $internal_form;
    protected bool $submission_created = false;
    protected Node $node;

    /**
     * @throws Exception
     * @throws FieldRequiredException
     * @throws FieldTypeSupportException
     */
    public function __construct(array $field, string $request_method, array $post = [], array $params = [], array $files = [])
    {

        parent::__construct($field, $request_method, $post, $params, $files);

        $supported_field_type = [
            'form_builder',
        ];

        $required = [
            'label',
            'id',
            'name',
        ];

        $this->validation_message = '';

        foreach ($required as $field_key) {
            if (!array_key_exists($field_key, $field)) {
                throw new FieldRequiredException('Field "' . $field_key . '" is required.');
            }
        }

        if (!in_array($field['type'], $supported_field_type)) {
            throw new FieldTypeSupportException('Field "' . $field['type'] . '" is not supported type.');
        }

        $this->field = $field;

        $form_builder_handler = FormConfigManager::factory()->getForm($this->field['settings']['show_as']);

        $this->internal_form = null;
        if (!empty($form_builder_handler)) {
            foreach ($form_builder_handler['fields'] as $field_name => $field) {
                if ($field['type'] === 'submit' || $field['type'] === 'reset' || $field['type'] === 'button') {
                    unset($form_builder_handler[$field_name]);
                }
            }
            if (Service::serviceManager()->request->get('nid')) {
                $form_builder_handler['fields']['nid'] = [
                    'type' => 'hidden',
                    'name' => 'nid',
                    'default_value' => Service::serviceManager()->request->get('nid'),
                    'label' => '',
                    'class' => [],
                    'id' => '',
                    'required' => true,
                    'options' => [],
                    'handler' => \Simp\Core\components\basic_fields\BasicField::class
                ];
                $form_builder_handler['fields']['node_field'] = [
                    'type' => 'hidden',
                    'name' => 'node_field',
                    'default_value' => $this->field['name'],
                    'label' => '',
                    'class' => [],
                    'id' => '',
                    'required' => true,
                    'options' => [],
                    'handler' => \Simp\Core\components\basic_fields\BasicField::class
                ];
                $this->node = Node::load(Service::serviceManager()->request->get('nid'));
            }
            $this->internal_form = new FormBuilder(new SubmissionFormHandler($form_builder_handler));
        }


        if ($request_method === 'POST') {

            $value = $post[$field['name']] ?? null;

            $fields = $this->internal_form->getFields();

            $submission = $this->internal_form->getFormBase()->getSubmission();
            if ($submission instanceof Submission) {
                $this->submission['value'] = $submission->getSid();
                $this->submission_created = true;
            }

        }

    }

    public function isSubmissionCreated(): bool
    {
        return $this->submission_created;

    }

    public function getLabel(): string
    {
        return $this->field['label'] ?? '';
    }

    public function getName(): string
    {
        return $this->field['name'] ?? '';
    }

    public function getType(): string
    {
        return $this->field['type'] ?? '';
    }

    public function getId(): string
    {
        return !empty($this->field['id']) ? $this->field['id'] : FieldManager::createFieldName($this->getLabel());
    }

    public function getClassList(): array
    {
        return $this->field['class'] ?? [];
    }

    public function getRequired(): string
    {
        return !empty($this->field['required']) ? 'required' : '';
    }

    public function getOptions(): array
    {
        return $this->field['options'] ?? [];
    }

    public function getDefaultValue(): string|int|float|null|array|bool
    {
        return $this->field['default_value'] ?? '';
    }

    public function getValue(): string|int|float|null|array|bool
    {
        return !empty($this->submission['value']) ? $this->submission['value'] : $this->field['default_value'] ?? '';
    }

    public function get(string $field_name): float|int|bool|array|string|null
    {
        return $this->getValue();
    }

    /**
     * @param bool $wrapper
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function getBuildField(bool $wrapper = true): string
    {
        $module_handler = ModuleHandler::factory();
        $module_handler->attachLibrary('page_builder', 'page.builder.library');

        $pid = $this->getValue();
        $pid = is_array($pid) ? $pid[0] : $pid;


        $page = Page::load(is_numeric($pid) ? $pid : 0);

        $status = $page->getStatus() == 1 ? 'Yes' : 'No';

        $wrapper_id = $this->getName() . "-wrapper-" . uniqid();

        return View::view('default.view.field_field_form_builder', [
            'field' => $this,
            'page' => $page,
            'wrapper_id' => $wrapper_id,
            '_form_builder_form' => $this->internal_form->__toOnlyFieldString(),
        ]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function __toString(): string
    {
        return $this->getBuildField();
    }

    public function setError(string $error): void
    {
        $this->validation_message = $error;
    }

    public function getDescription(): string
    {
        return $this->field['description'] ?? '';
    }

    public function getField(): array
    {
        return $this->field;
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
    public function display(string $field_type, FieldBase $field, array $context): string
    {
        /**@var Node $context['node'] */
        $sids = $context['node']->get($field->getName());

        $submissions = [];

        foreach ($sids as $sid) {
            if (!empty($sid)) {
                $submissions[] = Submission::load($sid);
            }
        }

        $field_array = $field->getField();
        $form_name = $field_array['settings']['show_as'];
        $settings = FormSettings::factory($form_name);
        $form = FormConfigManager::factory()->getForm($form_name);
        $data = ['title' => $settings->getTitle(), 'form' => $form,'submissions'=>$submissions, 'form_name'=> $form_name];
        $data['fields'] = $data['form']['fields'] ?? [];
        $context = ['definition' => $field, ...$context, ...$data];


        ModuleHandler::factory()->attachLibrary('form_builder', 'form.builder.library');

        $this->internal_form->getFormBase()->setFormMethod('POST');
        $this->internal_form->getFormBase()->setFormEnctype('multipart/form-data');
        $this->internal_form->getFormBase()->setFormAction(
            Route::url('form_builder.form.submission.node',['name'=>$form_name,'nid'=> $this->node->getNid(),'field'=>$field->getName()])
        );
        $context['form_visible'] = $field->getField()['settings']['form_visible'] ?? false;
        $context['__form_base'] = $this->internal_form;
        $context['__form_title'] = $settings->getTitle();
        $context['__form_submission_allowed'] = $context['form_visible'] === true ? uniqid() : '';
        return trim(View::view("default.view.node.field.form.builder", $context));
    }
}