<?php

namespace Simp\Core\lib\controllers;



use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\form\FormDefinitionBuilder;
use Simp\Core\lib\forms\ForgotPasswordForm;
use Simp\Core\lib\forms\ForgotPasswordResetForm;
use Simp\Core\lib\forms\LoginForm;
use Simp\Core\lib\forms\ProfileEditForm;
use Simp\Core\lib\forms\UserAccountForm;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\auth\AuthenticationSystem;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\entity\User;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class UserAccountFormController
{
    /**
     * @param ...$args
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function user_account_form_controller(...$args): Response
    {
        $config = ConfigManager::config()->getConfigFile('account.setting');
        $current = CurrentUser::currentUser();
        $redirect = new RedirectResponse('/');
        if ($config?->get('allow_account_creation') === 'administrator' && empty($current?->isIsAdmin())) {
            $redirect->send();
        }
        elseif ($config?->get('allow_account_creation') === 'visitor' && !empty($current?->isIsAdmin())) {
            Messager::toast()->addWarning("Access denied for this user");
            $redirect->send();
        }

        $form_base = FormDefinitionBuilder::factory()->getForm('user.entity.form');
        return new Response(View::view('default.view.user_account_form',['_form'=>$form_base]),200);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function user_login_form_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = FormDefinitionBuilder::factory()->getForm('user.entity.login.form');

        $authenticate = new AuthenticationSystem();
        $google_link = null;
        $github_link = null;
        if ($authenticate->isGoogleAuthActive()) {
            $google = $authenticate->getOauthInstance('google');
            $google_link = $google->generateLoginUrl();
        }

        if ($authenticate->isGithubAuthActive()) {
            $github = $authenticate->getOauthInstance('github');
            $github_link = $github->generateLoginUrl();
        }

        return new Response(View::view('default.view.view.login.form',[
            'auth' => $authenticate,
            'google_link' => $google_link,
            'github_link' => $github_link,
            '__form_base' => $form_base,
        ]),200);
    }


    public function user_account_password_form_controller(...$args): Response
    {
        $form_base = FormDefinitionBuilder::factory()->getForm('user.entity.password.form');
        return new Response(View::view('default.view.user_account_password_form',['_form'=>$form_base]),200);
    }

    public function user_account_password_reset_form_controller(...$args): Response|RedirectResponse
    {
        extract($args);
        $hash = $request->get("hash");
        if (!Caching::init()->has($hash)) {
            Messager::toast()->addError("Invalid hash");
            return new RedirectResponse('/');
        }
        $form_base = new FormBuilder(new ForgotPasswordResetForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.user_account_password_reset_form_controller',['_form'=>$form_base]),200);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function user_account_email_verify_form_controller(...$args):Response|RedirectResponse
    {
        extract($args);
        $request_token = $request->get("hash");
        $query = Database::database()->con()->prepare("SELECT * FROM verify_email_token WHERE token = :token");
        $query->execute([':token'=>$request_token]);
        $result = $query->fetch();
        if (!empty($result)) {

            $created_time = strtotime($result['created']);
            $now = time();
            $diff = $now - $created_time;
            if ($diff < 3600) {
                $user = User::load($result['uid']);
                $user->setStatus(true);
                $user->update();
                $query = Database::database()->con()->prepare("UPDATE verify_email_token SET verified = 1 WHERE token = :token");
                $query->execute([':token'=>$request_token]);
                Messager::toast()->addMessage("Email verified successfully");;
                return new RedirectResponse('/');
            }
        }

        return new Response("Sorry token expired or invalid",200);
    }


    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function account_profile_edit_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = FormDefinitionBuilder::factory()->getForm('user.entity.profile.form');
        return new Response(View::view('default.view.profile.form.edit', ['_form'=>$form_base]),200);
    }
}