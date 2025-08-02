<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\form\FormDefinitionBuilder;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\timezone\TimeZone;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\entity\User;
use Simp\Fields\FieldBase;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Yaml\Yaml;

class UserAccountForm extends FormBase
{

    protected array $entity_form = [];
    private bool $validated = true;
    private int $status = 0;
    private array $roles = [];


    public function __construct(mixed $options = [])
    {
        parent::__construct($options);

    }

    public function getFormId(): string
    {
       return 'user_account_form';
    }

    public function buildForm(array $form): array
    {
        $this->entity_form = parent::buildForm($form);

        $config = ConfigManager::config()->getConfigFile('account.setting');
        if ($config?->get('allow_account_creation') !== 'administrator') {
            if (!CurrentUser::currentUser()?->isIsAdmin()) {
                unset($this->entity_form['fields']['roles']);
                $this->roles = ['authenticated'];
            }
        }

        $timezone = new  TimeZone();
        $list = $timezone->getSimplifiedTimezone();
        sort($list);
        $this->entity_form['user_prefer_timezone']['option_values'] = $list;
        return $this->entity_form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field instanceof FieldBase) {
                if ($field->getRequired() === 'required' && empty($field->getValue())) {
                    $this->validated = false;
                }
            }
        }

        $config = ConfigManager::config()->getConfigFile('account.setting');

        if ($form['cond_password']->get('password') !== $form['cond_password']->get('password_confirm')) {
            $this->validated = false;
            $form['cond_password']->setError("Passwords do not match");
        }

        if ($config?->get('password_strength') === "high" || $config?->get('password_strength') === "medium") {

            $type = $config?->get('password_strength');
            if ($type === "high" && User::checkPasswordStrength($form['cond_password']->get('password')) !== 'high') {
                $form['cond_password']->setError("Password is not strong enough");
                $this->validated = false;
            }

            elseif ($type === "medium" && User::checkPasswordStrength($form['cond_password']->get('password')) === 'low') {
                $form['cond_password']->setError("Password is not strong enough");
            }
        }
    }

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function submitForm(array $form): void
    {
       if ($this->validated) {
          $user_data['name'] = $form['name']?->getValue();
          $user_data['mail'] = $form['mail']->getValue();
          $user_data['time_zone'] = $form['user_prefer_timezone']->getValue();
          $user_data['password'] = $form['cond_password']->get('password');

          if (empty($form['roles'])) {
              $user_data['roles'] = $this->roles;
          }else {
              $user_data['roles'][] = $form['roles']?->getValue();
          }

          $timezone = new TimeZone();
          $list = array_keys($timezone->getSimplifiedTimezone());
          sort($list);
          $user_data['time_zone'] = $list[$user_data['time_zone']] ?? "Africa/Blantyre";

          $user = User::create($user_data);
          if ($user === false) {
              Messager::toast()->addError("Unable to create account due to already existing name or email");
          }
          elseif ($user === null) {
              Messager::toast()->addError("Unable to create account due to incomplete data");
          }
          else {
              Messager::toast()->addMessage("Account created successfully");
              $redirect = new RedirectResponse('/');
              $redirect->send();
          }
       }
    }
}