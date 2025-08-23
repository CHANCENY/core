<?php

namespace Simp\Core\extends\form_builder\src\Form;

use Simp\Core\extends\form_builder\src\Plugin\FormSettings;
use Simp\Core\extends\form_builder\src\Plugin\Submission;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\helpers\FileFunction;
use Simp\Core\modules\files\uploads\FormUpload;
use Simp\Core\modules\mail\MailManager;
use Simp\Core\modules\mail\MailQueueManager;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Default\FileField;
use Simp\FormBuilder\FormBase;
use Simp\Mail\Mail\Envelope;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SubmissionFormHandler extends FormBase
{
    protected bool $validated = true;
    protected mixed $options;
    protected array $fields;
    protected string $form_id;

    public function __construct(mixed $options = [])
    {
        parent::__construct($options);

        $this->fields = $options['fields'] ?? [];
        $this->form_id = $options['form_id'] ?? $options['name'] ?? '';
    }

    public function getSubmission(): Submission|null
    {
        return $this->options['submission'] ?? null;
    }

    public function getFormId(): string
    {
       return 'submission_form_'.$this->form_id;
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);
        $form = array_merge($form, $this->fields);
        foreach ($form as $e=>$field) {
            $form[$e]['handler'] = str_replace('\\\\', '\\', $field['handler']);
        }
        return $form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {

            /**@var FileField $field */
            if ($field->getRequired() === 'required' && empty($field->getValue())) {
                $field->setError('This field is required.');
                $this->validated = false;
            }
        }
    }

    public function submitForm(array $form): void
    {
        if ($this->validated) {
            $submission = new Submission(form_name: $this->form_id);
            $fields = $submission->getFields();

            $values = array();

            foreach ($fields as $key=>$field) {

                $value = $form[$key]->getValue();

                if (!is_array($value)) {
                    $values[$key] = [$value];
                }
                else {
                    $values[$key] = $value;
                }

            }

            foreach ($values as $key=>$value) {

                if ($fields[$key]['type'] === 'file') {

                     if (!empty($value['name'])) {

                         $upload = new FormUpload();
                         foreach ($fields[$key]['settings']['allowed_file_types'] as $extension) {
                             $upload->addAllowedExtension($extension);
                         }
                         $upload->addAllowedMaxSize($fields[$key]['settings']['allowed_file_size']);
                         $upload->addFileObject($value);
                         $upload->validate();

                         if (!$upload->isValidated()) {
                             $types = "File type allowed: ".implode(', ', $fields[$key]['settings']['allowed_file_types'])."<br>";
                             $types .= "Max size allowed: ".FileFunction::sizeTransform($fields[$key]['settings']['allowed_file_size']) ." Bytes<br>";
                             $form[$key]->setError("<br>Failed to upload file. please check that the file is valid and try again.".$types);
                             return;
                         }

                         if (!is_dir("public://forms-uploads")) {
                             @mkdir("public://forms-uploads", 0777, true);
                         }

                         $filename = "public://forms-uploads/". $upload->getParseFilename();
                         $upload->moveFileUpload($filename);

                         $files = $upload->getFileObject();
                         $files['uid'] = CurrentUser::currentUser()->getUser()->getUid();
                         $files['uri'] = $files['file_path'];

                         $file = File::create($files);
                         if ($file) {
                             $values[$key] = [$file->getFid()];
                         }

                     }
                     else {
                         $value = is_array($value) ? reset($value): $value;
                         $value = is_string($value) ? json_decode($value, true) : $value;
                         $values[$key] = $value;
                     }

                }

                else {
                    $values[$key] = $value;
                }
            }

            $submission = $submission->create($values);
            $setting = FormSettings::factory($this->form_id);
            $this->options['submission'] = $submission;

            if (!empty($submission->getCreatedAt())) {

                if (!empty($setting->getConfirmation())) {
                    Messager::toast()->addMessage($setting->getConfirmation());
                }
            }

            if (!empty($setting->getNotify())) {
                $submission = Submission::load($submission->getSid());
                $fields = $submission->getFields();
                $message = View::view('default.view.form_builder.submission_view.email',['submission'=>$submission, 'fields'=>$fields, 'form_name'=>$this->form_id]);
                MailQueueManager::factory()->add((new Envelope())->addToAddresses([
                    $setting->getNotify()
                ])->addParam('subject' , 'Form Submission - '. $setting->getFormName())
                ->addParam('body' , $message));
            }
        }

    }
}