<?php

namespace Simp\Core\lib\controllers;

use AddCronForm;
use Simp\Core\components\form\FormDefinitionBuilder;
use Simp\Core\components\rest_data_source\DefaultDataSource;
use Simp\Core\lib\forms\ContentTypeInnerFieldEditForm;
use Simp\Core\lib\forms\DisplayEditForm;
use Simp\Core\lib\forms\SearchFormConfiguration;
use Simp\Core\lib\forms\ViewAddForm;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\assets_manager\AssetsManager;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\integration\rest\JsonRestManager;
use Simp\Core\modules\logger\DatabaseLogger;
use Simp\Core\modules\logger\ErrorLogger;
use Simp\Core\modules\logger\ServerLogger;
use Simp\Core\modules\search\SearchManager;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Core\modules\structures\content_types\form\ContentTypeDefinitionEditForm;
use Simp\Core\modules\structures\taxonomy\Term;
use Simp\Core\modules\structures\views\ViewsManager;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;
use Google\Service\Exception;
use Simp\Core\lib\themes\View;
use Simp\FormBuilder\FormBuilder;
use Simp\Core\lib\forms\LoginForm;
use Simp\Core\lib\forms\SiteSmtpForm;
use Simp\Core\modules\user\entity\User;
use Simp\Core\lib\forms\ContentTypeForm;
use Simp\Core\lib\forms\DevelopmentForm;
use Simp\Core\lib\forms\ProfileEditForm;
use Simp\Core\modules\messager\Messager;
use GuzzleHttp\Exception\GuzzleException;
use Simp\Core\lib\forms\BasicSettingForm;
use Simp\Core\lib\memory\session\Session;
use Simp\Core\lib\forms\AccountSettingForm;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\lib\forms\ContentTypeEditForm;
use Simp\Core\lib\forms\UserAccountEditForm;
use Simp\Core\lib\forms\ContentTypeFieldForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Simp\Core\modules\auth\AuthenticationSystem;
use Simp\Core\modules\auth\normal_auth\AuthUser;
use Simp\Core\lib\forms\ContentTypeFieldEditForm;
use Simp\Core\lib\forms\ContentTypeInnerFieldForm;
use Symfony\Component\HttpFoundation\JsonResponse;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Simp\Core\modules\user\current_user\CurrentUser;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Simp\Core\modules\structures\content_types\entity\Node;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Simp\Core\lib\forms\AddCronForm as FormsAddCronForm;
use Simp\Core\modules\cron\Cron;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\form\ContentTypeDefinitionForm;

class SystemController
{
    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function toastify_controller(...$args): JsonResponse
    {
        $messages = Session::init()->get('system.messages');
        Session::init()->delete('system.messages');
        return new JsonResponse($messages);
    }

    public function system_error_page_denied(...$args): Response
    {
        return new Response("Access denied. sorry you can acess this page", 403);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function system_user_filter_auto_page(...$args): JsonResponse
    {
        extract($args);
        $name = $request->get('content_name');
        $value = $request->get('search');
        $content_type = ContentDefinitionManager::contentDefinitionManager()->getContentType($name);
        if ($content_type) {
            $permission = $content_type['permission'] ?? [];
            $user_roles = CurrentUser::currentUser()?->getUser()?->getRoles();
            $flag = false;
            if ($user_roles) {
                $roles = array_map(function($role){return $role->getRoleName(); }, $user_roles);
                if(array_intersect($permission, $roles)) {
                    $flag = true;
                }
                if (CurrentUser::currentUser()->isIsAdmin()) {
                    $flag = true;
                }
            }
            elseif(in_array('anonymous', $permission)) {
                $flag = true;
            }

            if($flag) {

                $users = User::filter($value);
                if (!empty($users)) {
                    $users = array_column($users, 'name');
                }
                return new JsonResponse($users, 200);
            }
        }
        return new JsonResponse(['user'=>1],200);
    }

    public function system_reference_filter(...$args): JsonResponse
    {
        extract($args);
        $content = json_decode($request->getContent());
        if ($content->value && $content->settings) {

            if(isset($content->settings->type) && $content->settings->type === 'user') {
                $users = User::filter($content->value);
                foreach ($users as $key => $user) {
                    if ($key === 'password') {
                        unset($users[$key]);
                    }
                }
                return new JsonResponse(['result'=> array_values($users)], 200);
            }
            elseif(isset($content->settings->type) && !empty($content->settings->reference_entity) && $content->settings->type === 'node') {
                $content_type = $content->settings->reference_entity;
                $nodes = Node::filter($content->value, $content_type);
                $nodes = array_map(function ($node) { return $node->toArray(); }, $nodes);
                return new JsonResponse(['result'=> array_values($nodes)], 200);
            }

            elseif (isset($content->settings->type) && $content->settings->type === 'file') {
                $files = File::search($content->value);
                $files = array_map(function ($file) { return $file->toArray(); }, $files);
                return new JsonResponse(['result'=> array_values($files)], 200);
            }

            elseif (isset($content->settings->type) && $content->settings->type === 'term') {
                $terms = Term::search($content->value);
                $list = array_map(function ($term) { return ['id'=>$term['id'], 'title'=>$term['label']]; }, $terms);
                return new JsonResponse(['result'=> $list], 200);
            }
        }
        return new JsonResponse(['result'=>'ok']);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function people_controller(...$args): Response
    {
        extract($args);
        /**@var Request $request**/
        $limit = $request->get('limit', 10);
        $limit = empty($limit) ? 10 : $limit;
        $filters = User::filters('users', $limit);
        $users = User::parseFilter(User::class, 'users', $filters, $request, User::class);
        return new Response(View::view('default.view.people',['users' => $users, 'filters' => $filters]));
    }

    /**
     * @param ...$args
     * @return RedirectResponse|Response
     * @throws LoaderError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function account_delete_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        /**@var Request $request**/

        // Let's look for account delete confirmation.
        $is_confirmed = $request->get('confirm', null);
        if ($is_confirmed === "yes") {
            Session::init()->set('system.deletion.confirmation', 'yes');
            $config = ConfigManager::config()->getConfigFile('account.setting');
            $user = User::load($request->get('uid'));

            if ($config?->get('cancellation') === 'blocked') {

                $user->setStatus(0);
                if ($user->update()) {
                    return new RedirectResponse('/admin/people');
                }

            }
            elseif ($config?->get('cancellation') === 'unpublished') {
                $user->setStatus(0);
                //TODO: update the nodes.
                if ($user->update()) {
                    return new RedirectResponse('/admin/people');
                }
            }

            if (User::dataDeletion('users', 'uid', $request->get('uid'))) {
                return new RedirectResponse('/admin/people');
            }
        }
        elseif ($is_confirmed === "no") {
            return new RedirectResponse('/user/'. $request->get('uid'));
        }

        return new Response(View::view('default.view.delete_confirmation',[
            'title' => "Account Deletion Confirmation",
            'message' => "You are requesting to delete your account. Are you sure you want to proceed?",
        ]));
    }

    public function assets_loader_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $name = $request->get('name');
        $file_path = (new AssetsManager())->getAssetsFile($name,false);

        if (!empty($file_path) && file_exists($file_path)) {
            $mime_type = mime_content_type($file_path);
            $mime_type = str_ends_with($name, '.js') ? 'application/javascript' : $mime_type;
            $response = new Response(
                file_get_contents($file_path),
            );
            $response->headers->set('Content-Type', $mime_type);
            $response->setStatusCode(200);
            return $response;
        }
        return new Response('', 404);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function account_edit_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = FormDefinitionBuilder::factory()->getForm('user.entity.edit.form');
        return new Response(View::view('default.view.edit.account.form',['_form'=>$form_base]),200);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function account_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $user = User::load($request->get('uid'));
        return new Response(View::view('default.view.view.account', ['user'=>$user]),200);
    }

   
    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function user_logout_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $auth = CurrentUser::currentUser();
        if ($auth->logout()) {
            return new RedirectResponse('/');
        }
        return new RedirectResponse($request->server->get('REDIRECT_URL'));
    }


    /**
     * @param ...$args
     * @return RedirectResponse|Response
     * @throws Exception
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function user_login_google_redirect_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        /**@var Request $request**/
        $google_code = $request->get('code');
        $authenticate = new AuthenticationSystem();
        $google_instance = $authenticate->getOauthInstance('google');
        if ($google_code) {

            $token = $google_instance->fetchAccessTokenWithAuthCode($google_code);
            $google_instance->setAccessToken($token);
            $user = $google_instance->oauth2Profile();
            $auth = AuthUser::auth();
            if ($auth->authenticateViaGoogle($user)) {
                $auth->finalizeAuthenticate(false);
                Messager::toast()->addMessage("Welcome back, {$auth->getUser()->getName()}!");
                return new RedirectResponse('/');
            }
            Messager::toast()->addError("Sorry login via google account has failed.");
            return new RedirectResponse('/user/login');
        }
        return new Response('');
    }

    /**
     * @param ...$args
     * @return RedirectResponse|Response
     * @throws GuzzleException
     * @throws IdentityProviderException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function user_login_github_redirect_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $github_code = $request->get('code');
        $authenticate = new AuthenticationSystem();
        $github_instance = $authenticate->getOauthInstance('github');
        if ($github_code) {
            $token = $github_instance->getAccessToken($github_code);
            $git_user = $github_instance->getResourceOwner($token);
            $auth = AuthUser::auth();
            if ($auth->authenticateViaGithub($git_user)) {
                $auth->finalizeAuthenticate(false);
                Messager::toast()->addMessage("Welcome back, {$auth->getUser()->getName()}!");
                return new RedirectResponse('/');
            }else {
                Messager::toast()->addError("Sorry login via github account has failed.");
                return new RedirectResponse('/user/login');
            }
        }
        return new RedirectResponse('/');
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function configuration_controller(...$args): RedirectResponse|Response
    {
        return new Response(View::view('default.view.configuration', ['_form']), 200);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function configuration_basic_site_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new BasicSettingForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.configuration.basic.site', ['_form'=>$form_base]), 200);
    }

    public function configuration_account_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new AccountSettingForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.configuration.accounts', ['_form'=>$form_base]), 200);
    }

    public function configuration_smtp_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new SiteSmtpForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.configuration.smtp', ['_form'=>$form_base]), 200);
    }

    public function configuration_logger_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new DevelopmentForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.configuration.logger', ['_form'=>$form_base]), 200);
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function structure_controller(...$args): RedirectResponse|Response
    {
        return new Response(View::view('default.view.structure'), 200);
    }

    public function structure_content_type_controller(...$args): RedirectResponse|Response
    {
        $manager = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        return new Response(View::view('default.view.content-types-listing',['items'=>$manager]), 200);
    }

    public function structure_content_type_form_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new ContentTypeForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.structure_content_type',['_form'=> $form_base]), 200);
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function content_type_edit_form_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $name = $request->get('machine_name');
        $content = ContentDefinitionManager::contentDefinitionManager()->getContentType($name);
        $form_base = new FormBuilder(new ContentTypeEditForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.structure_content_type_edit',['_form'=> $form_base, 'content'=>$content]), 200);
    }

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function content_type_delete_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $name = $request->get('machine_name');
        $content = ContentDefinitionManager::contentDefinitionManager()->getContentType($name);
        if ($content && ContentDefinitionManager::contentDefinitionManager()->removeContentType($name)) {
            Messager::toast()->addMessage("Content type \"$name\" successfully removed.");
        }
        return new RedirectResponse('/admin/structure/types');
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function content_type_manage_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $name = $request->get('machine_name');
        $content = ContentDefinitionManager::contentDefinitionManager()->getContentType($name);
        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            if(isset($data['display_submit'])) {
                $settings = $content['display_setting'] ?? [];
                $storages = $content['storage'] ?? [];
                foreach($storages as $storage) {
                    $name_field = substr($storage, 5, strlen($storage));
                    $name_field = trim($name_field, '_');
                    $settings[$name_field]['display_label'] = $data[$name_field . ':display_label'] ?? $settings[$name_field]['display_label'] ?? null;
                    $settings[$name_field]['display_as'] = $data[$name_field . ':display_as'] ?? $settings[$name_field]['display_as'] ?? null;
                    $settings[$name_field]['display_enabled'] = $data[$name_field . ':display_enabled'] ?? $settings[$name_field]['display_enabled'] ?? null;
                }
                $content['display_setting'] = $settings;
                ContentDefinitionManager::contentDefinitionManager()->addContentType($name, $content);
                Messager::toast()->addMessage("Display setting saved");
                return new RedirectResponse('/admin/structure/content-type/'.$name.'/manage');
            }
            elseif (isset($data['permission_submit'])) {
                $permission = $data['permission'];
                $content['permission'] = $permission;
                ContentDefinitionManager::contentDefinitionManager()->addContentType($name, $content);
                Messager::toast()->addMessage("Content type permission saved");
                return new RedirectResponse('/admin/structure/content-type/'.$name.'/manage');
            }
        }
        $content['permission'] = $content['permission'] ?? [];
        $content['display_setting'] = $content['display_setting'] ?? [];
        return new Response(View::view('default.view.structure_content_type_manage',['content'=>$content]), 200);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function content_type_manage_add_field_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $fields_supported = FieldManager::fieldManager()->getSupportedFieldsType();
        return new Response(View::view('default.view.structure_content_type_manage_add_field',['field_types'=>$fields_supported]), 200);
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function content_type_manage_add_field_type_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $type = $request->get('type');
        $entity_type = $request->get('machine_name');
        $handler = FieldManager::fieldManager()->getFieldBuilderHandler($type);
        if ($request->getMethod() === 'POST') {
            $data = $handler->fieldArray($request,$type, $entity_type);
            if (!empty($data)) {
                if (ContentDefinitionManager::contentDefinitionManager()->addField(
                    $entity_type,
                    $data['name'],
                    $data,
                    true,
                )) {
                    Messager::toast()->addMessage("Field '$name' has been created");
                }
                $redirect = new RedirectResponse('/admin/structure/content-type/'.$entity_type. '/manage');
                $redirect->send();
            }else {
                Messager::toast()->addError("Failed to create field. Please check your input and try again.");
            }
        }
        $build = $handler->build($request,$type);
        return new Response(View::view('default.view.structure_content_type_manage_add_type_field',
            ['form'=>$build,'field' => FieldManager::fieldManager()->getFieldInfo($type)]), 200);
    }

    public function content_type_manage_edit_field_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $field = ContentDefinitionManager::contentDefinitionManager()->getField(
            $request->get('machine_name'),
            $request->get('field_name')
        );

        if(empty($field['type'])) {
            return new RedirectResponse($request->headers->get('referrer') ?? '/');
        }

        $type = '';
        if ($field['type'] === 'textarea' && !empty($field['class']) && in_array('editor',$field['class'])) {
            $type = 'ck_editor';
        }
        elseif ($field['type'] === 'textarea' && !empty($field['class']) && !in_array('editor',$field['class'])) {
            $type = 'simple_textarea';
        }
        else {
            $type = $field['type'];
        }

        $field['type'] = $type;
        $handler = FieldManager::fieldManager()->getFieldBuilderHandler($field['type']);
        $entity_type =  $request->get('machine_name');
        $field_name =  $request->get('field_name');

        if ($request->getMethod() === 'POST') {
            $data = $handler->fieldArray($request,$field['type'], $entity_type);
            if (!empty($data)) {
                $data['name'] = $field_name;
                if (ContentDefinitionManager::contentDefinitionManager()->addField(
                    $entity_type,
                    $data['name'],
                    $data,
                    true,
                )) {
                    Messager::toast()->addMessage("Field '$name' has been updated");
                }
                $redirect = new RedirectResponse('/admin/structure/content-type/'.$entity_type. '/manage');
                $redirect->send();
            }else {
                Messager::toast()->addError("Failed to update field. Please check your input and try again.");
            }
        }


        $build = $handler->build($request, $field['type'], $field);

        return new Response(View::view('default.view.structure_content_type_manage_edit_field',
        [
            '_form'=>$build ,
            'field' => FieldManager::fieldManager()->getFieldInfo($field['type'])
        ]
        ), 200);
    }

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function content_type_manage_delete_field_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $name = $request->get('machine_name');
        $field_name = $request->get('field_name');
        if (ContentDefinitionManager::contentDefinitionManager()->removeField($name,$field_name)) {
            Messager::toast()->addMessage("Content type field \"$field_name\" successfully removed.");
        }
        return new RedirectResponse('/admin/structure/content-type/'.$name.'/manage');
    }

    public function content_node_add_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new ContentTypeDefinitionForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        $content = $request->get('content_name');
        if (empty($content)) {
            Messager::toast()->addWarning("Content type not found.");
            return new RedirectResponse('/');
        }
        $content = ContentDefinitionManager::contentDefinitionManager()->getContentType($content);
        return new Response(View::view('default.view.content_node_add',['_form'=>$form_base, 'content' => $content]), 200);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function content_content_admin_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        /**@var Request $request**/
        /**@var Request $request**/
        $limit = $request->get('limit', 10);
        $limit = empty($limit) ? 10 : $limit;

        $filters = Node::filters('node_data', $limit);
        $files_filters = File::filters('file_managed', $limit);

        $files = [];
        $nodes = [];
        if (!empty($request->get('search_by'))) {

            if ($request->get('storage') === 'file_managed') {
                $files = File::parseFilter(File::class, 'file_managed',
                    $files_filters, $request, File::class);
            }
            elseif ($request->get('storage') === 'node_data') {
                $nodes = Node::parseFilter(Node::class, 'node_data', $filters, $request, Node::class);
            }
        }
        else {
            $nodes = Node::parseFilter(Node::class, 'node_data', $filters, $request, Node::class);
            $files = File::parseFilter(File::class, 'file_managed',
                $files_filters, $request, File::class);
        }

        return new Response(View::view('default.view.content_content_admin',
            [
                'nodes' => $nodes,
                'filters' => $filters,
                'files_filters' => $files_filters,
                'files' => $files,
            ]
        ),
            200);
    }

    public function content_content_node_add_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $content_list = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        return new Response(View::view('default.view.content_content_admin_node_add',['contents' => $content_list]), 200);
    }

    public function content_structure_field_inner_manage_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $name = $request->get('machine_name');
        $fields = ContentDefinitionManager::contentDefinitionManager()
                  ->getContentType($name);
        $inner_fields = $fields['fields'][$request->get('field_name')]['inner_field'] ?? [];

        $content = ContentDefinitionManager::contentDefinitionManager()->getContentType($name);
        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            if (isset($data['display_submit'])) {
                $settings = $content['display_setting'] ?? [];
                $storages = $content['storage'] ?? [];
                foreach ($storages as $storage) {
                    $name_field = substr($storage, 5, strlen($storage));
                    $name_field = trim($name_field, '_');
                    $settings[$name_field]['display_label'] = $data[$name_field . ':display_label'] ?? $settings[$name_field]['display_label'] ?? null;
                    $settings[$name_field]['display_as'] = $data[$name_field . ':display_as'] ?? $settings[$name_field]['display_as'] ?? null;
                    $settings[$name_field]['display_enabled'] = $data[$name_field . ':display_enabled'] ?? $settings[$name_field]['display_enabled'] ?? null;
                }
                $content['display_setting'] = $settings;
                ContentDefinitionManager::contentDefinitionManager()->addContentType($name, $content);
                Messager::toast()->addMessage("Display setting saved");
                return new RedirectResponse('/admin/structure/content-type/' . $name . '/manage');
            }
        }

        $content['display_setting'] = $content['display_setting'] ?? [];

        return new Response(View::view('default.view.content_structure_field_inner_manage',
         ['fields'=>$inner_fields, 'content'=> $fields, 'parent_field'=> $request->get('field_name')]));
    }

    public function content_structure_field_inner_add_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new ContentTypeInnerFieldForm);
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.structure_content_type_manage_add_field', ['_form' => $form_base, 'parent_field'=>$request->get('field_name')]), 200);
    }

    public function content_structure_field_inner_edit_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new ContentTypeInnerFieldEditForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.structure_content_type_manage_add_field', ['_form' => $form_base, 'parent_field'=>$request->get('field_name')]), 200);
    }

    public function content_type_manage_delete_inner_field_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $name = $request->get('machine_name');
        $field_name = $request->get('field_name');
        $parent_field = $request->get('parent_name');
        if (ContentDefinitionManager::contentDefinitionManager()->removeInnerField($name,$parent_field,$field_name)) {
            Messager::toast()->addMessage("Content type field \"$field_name\" successfully removed.");
        }
        return new RedirectResponse('/admin/structure/content-type/'.$name.'/manage');
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws \ReflectionException
     */
    public function content_node_controller(...$args): RedirectResponse|Response
    {
        extract($args);

        $nid = $request->get('nid');

        /**@var Route|null $route**/
        $route = $options['route'] ?? null;

        $default = $route->options['default'] ?? null;

        if (!empty($default) && $default !== $route->route_id) {
            $route_object = Caching::init()->get($default);
            if ($route_object instanceof Route) {
                return Route::getControllerResponse($route_object, $args);
            }
        }


        if (empty($nid)) {
            if (!empty($route)) {
                $route_option = $route->options['node'] ?? null;

                if (!empty($route_option)) {
                    $nid = $route_option;
                }
            }
        }

        if (empty($nid)) {
            Messager::toast()->addWarning("Node id not found.");
            return new RedirectResponse('/');
        }
        try{
            $node = Node::load($nid);
            $entity = $node->getEntityArray();
            $options['route']->route_title = $node->getTitle();
            $definitions = [];
            foreach ($entity['storage'] as $field) {
                $name = substr($field,6,strlen($field));
                $definitions[$name] = Node::findField($entity['fields'], $name);
            }
            $content_definitions = ContentDefinitionManager::contentDefinitionManager()->getContentType($node->getBundle());
            return new Response(View::view('default.view.content_node_controller',[
                'node'=>$node,
                'definitions'=>$definitions,
                'display' => $content_definitions['display_setting'] ?? []
            ]));
        }catch (Throwable $exception){
            return new RedirectResponse('/');
        }
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function content_node_add_edit_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $nid = $request->get('nid');
        if (empty($nid)) {
            Messager::toast()->addWarning("Node id not found.");
            return new RedirectResponse('/');
        }
        $form_base = new FormBuilder(new ContentTypeDefinitionEditForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        $obj = Node::load($nid);
        if (is_null($obj)) {
            Messager::toast()->addWarning("Node not found");
            return new Response('/');
        }
        $content = ContentDefinitionManager::contentDefinitionManager()->getContentType($obj->getBundle());
        return new Response(View::view('default.view.content_node_add',['_form'=>$form_base, 'content' => $content]), 200);
    }

    /**
     * @throws RuntimeError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     */
    public function content_node_add_delete_controller(...$args): RedirectResponse|Response
    {
        extract($args);

        $redirect_path = '/';
        if (CurrentUser::currentUser()->isIsAdmin()) {
            $redirect_path = '/admin/content';
        }
        $nid = $request->get('nid');
        if (empty($nid)) {
            Messager::toast()->addWarning("Node id not found.");
            return new RedirectResponse('/');
        }
        $node = Node::load($nid);
        if (is_null($node)) {
            Messager::toast()->addWarning("Node not found");
            return new RedirectResponse($redirect_path);
        }
        if (empty($request->get('action'))) {
            return new Response(View::view('default.view.confirm.content_node_delete',['node'=>$node]));
        }
        $title = $node->getTitle();
        if ((int) $request->get('action') == 3) {
            return new RedirectResponse('/node/'.$node->getNid());
        }
        if ($node->delete((int) $request->get('action'))) {
            Messager::toast()->addMessage("Node \"$title\" successfully deleted.");
            return $request->get('action') == 1 ? new RedirectResponse($redirect_path) : new RedirectResponse('/node/'.$node->getNid());
        }
        Messager::toast()->addWarning("Node \"$title\" was not deleted.");
        return new RedirectResponse('/node/'.$node->getNid());
    }


    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function admin_report_site_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $offset =  $request->get('offset', 1);
        $limit =  $request->get('limit', 124);
        $server = new ServerLogger(limit: $limit, offset: $offset);
        $errors = new ErrorLogger(read: true);
        $database = new DatabaseLogger();
        return new Response(View::view('default.view.admin_report_site_controller',
            [
                'server'=>$server->getLogs(),
                'server_filter' => $server->getFilterNumber(2),
                'offset' => $offset,
                'limit' => $limit,
                'errors'=>$errors->getLogs(),
                'database_logs' => $database->logs()
            ]
        ), 200);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function content_views_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $views_listing = ViewsManager::viewsManager()->getViews();
        return new Response(View::view('default.view.content_views_controller', ['views'=>$views_listing]));
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function content_views_add_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $form_base = new FormBuilder(new ViewAddForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.content_views_add_controller', ['_form'=>$form_base]));
    }

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function content_views_view_delete_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $view_name = $request->get('view_name');
        if (empty($view_name)) {
            Messager::toast()->addWarning("View name not found.");
            return new RedirectResponse('/admin/structure/views');
        }
        if (ViewsManager::viewsManager()->removeView($view_name)) {
            Messager::toast()->addMessage("View \"$view_name\" was successfully removed.");
            return new RedirectResponse('/admin/structure/views');
        }
        Messager::toast()->addWarning("View \"$view_name\" was not removed.");
        return new RedirectResponse('/admin/structure/views');
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function content_views_view_edit_controller(...$args): RedirectResponse|Response
    {
        extract($args);
        $view_name = $request->get('view_name');
        if (empty($view_name)) {
            Messager::toast()->addWarning("View name not found.");
            return new RedirectResponse('/admin/structure/views');
        }
        $view = ViewsManager::viewsManager()->getView($view_name);
        $form_base = new FormBuilder(new ViewAddForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');

        return new Response(View::view('default.view.content_views_edit_controller', ['view'=>$view, '_form'=>$form_base]));
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function content_views_view_display_controller(...$args): RedirectResponse|Response|JsonResponse
    {
        extract($args);

        $view_name = $request->get('view_name');
        $content_field = json_decode($request->getContent(), true);

        if (!empty($content_field['delete_field_setting']) && $content_field['delete_field_setting'] === true) {
            $content_field = $content_field['data'] ?? [];
            $list = explode('|', $content_field['field']);
            $section = $list[0] ?? false;
            $display_name = $content_field['display_name'] ?? false;
            $key = $list[1].'|'.$list[2];

            if (empty($display_name) || empty($view_name) || empty($key) || empty($section)) {
                return new JsonResponse(['result'=>false, 'message'=>'Invalid parameters.']);
            }
            $result = ViewsManager::viewsManager()->removeDisplayFieldSetting($display_name,$key,$section);
            return new JsonResponse(['result'=>$result, 'message'=>'Display field setting removed.']);
        }
        if (!empty($content_field['delete']) && $content_field['delete'] === true) {

            if (!empty($content_field['display_name']) && !empty($view_name)) {
                $result  = ViewsManager::viewsManager()->removeDisplay($view_name, $content_field['display_name']);
                return new JsonResponse(['result'=>$result]);
            }
        }

        if (!empty($content_field) && !isset($content_field['reorder']) && !isset($content_field['setting'])) {
            $display = ViewsManager::viewsManager()->getDisplay($content_field['display']);
            $fields = $display[$content_field['type']] ?? [];
            $key = $content_field['content_type']. "|". $content_field['field'];
            $fields[$key] = $content_field;
            $display[$content_field['type']] = $fields;
            $result = ViewsManager::viewsManager()->addFieldDisplay($content_field['display'], $display);
            return new JsonResponse(['result'=>$result]);
        }

        if (!empty($content_field['reorder'])) {
            $data = $content_field['reorder'];
            $fields = $data['fields'] ?? [];
            $sort_fields = $data['sort_criteria'] ?? [];
            $filter_fields = $data['filter_criteria'] ?? [];

            $view_fields = ViewsManager::viewsManager()->getDisplay($content_field['display']);

            uksort($view_fields['fields'], function ($a, $b) use ($fields) {
                return array_search($a, $fields) - array_search($b, $fields);
            });
            uksort($view_fields['sort_criteria'], function ($a, $b) use ($sort_fields) {
                return array_search($a, $sort_fields) - array_search($b, $sort_fields);
            });
            uksort($view_fields['filter_criteria'], function ($a, $b) use ($filter_fields) {
                return array_search($a, $filter_fields) - array_search($b, $filter_fields);
            });

            $content_field['more_display_settings']['custom_params'] = explode(',', $content_field['more_display_settings']['custom_params'] ?? '');
            $content_field['more_display_settings']['custom_params'] = array_map('trim', $content_field['more_display_settings']['custom_params'] ?? []);
            $content_field['more_display_settings']['custom_params'] = array_values($content_field['more_display_settings']['custom_params']);
            $view_fields['settings'] = $content_field['more_display_settings'] ?? [];
            $view_fields['params'] = [...$view_fields['params'] ?? [], ...$content_field['more_display_settings']['custom_params']];
            $view_fields['params'] = array_unique($view_fields['params']);

            $result = ViewsManager::viewsManager()->addFieldDisplay($content_field['display'], $view_fields);
            return new JsonResponse(['result'=>$result]);
        }

        if (!empty($content_field['setting']) && $content_field['setting'] == 'settings') {
            $data = $content_field['data'] ?? [];
            $target = $data['target'] ?? '';
            if (!empty($target)) {
                $list = explode('|', $target);
               $view_fields = ViewsManager::viewsManager()->getDisplay($data['display_name']);
               $view_fields[$list[0]][$list[1].'|'.end($list)]['settings'] = $data['settings'] ?? [];
               $result = ViewsManager::viewsManager()->addFieldDisplay($data['display_name'], $view_fields);
                return new JsonResponse(['result'=>$result]);
            }
        }

        if (empty($view_name)) {
            Messager::toast()->addWarning("View name not found.");
            return new RedirectResponse('/admin/structure/views');
        }

        if ($request->getMethod() == 'POST' && $request->request->has('submit-new-display')) {
            $data = $request->request->all();

            if (!empty($data['display_name']) && !empty($data['display_url']) && !empty($data['response_type'])) {

                $view = ViewsManager::viewsManager()->getView($view_name);
                $name = strtolower($data['display_name']);
                $name = str_replace(' ', '_', $name);
                $display = [
                    'name' => $data['display_name'],
                    'display_name' => $name,
                    'response_type' => $data['response_type'],
                    'content_type' => $view['content_type'],
                    'template' => '',
                    'params' => '',
                    'permission' => $data['permission'] ?? $view['permission'] ?? [],
                    'display_url' => $data['display_url'],
                    'fields' => [],
                    'filter_criteria'=> [],
                    'sort_criteria' => []
                ];
                $list = explode('/',  $data['display_url']);
                if (!empty($list)) {
                    $place_holders = array_map(function ($part) {
                        if (str_starts_with($part,'[') && str_ends_with($part,']')) {
                            $part = substr($part, 1, -1);
                            $list = explode(':', $part);
                            return $list[0];
                        }
                        else {
                            return null;
                        }
                    }, $list);
                    $place_holders = array_values(array_filter($place_holders));
                    $display['params'] = $place_holders;
                }

                if (ViewsManager::viewsManager()->addViewDisplay($view_name, $display)) {
                    Messager::toast()->addMessage("Display \"$view_name\" was successfully added.");
                    return new RedirectResponse("/admin/structure/views/view/$view_name/displays");
                }
            }

        }

        $view = ViewsManager::viewsManager()->getView($view_name);
        $types = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        $types = array_keys($types);
        $contents = $view['content_type'] === 'all' ? $types : [$view['content_type']];
        $list = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        $fields = [];

        foreach ($list as $content) {
            $storages = $content['storage'] ?? [];
            foreach ($storages as $storage) {
                $name = substr($storage,6, strlen($storage));
                $field = Node::findField($content['fields'] ?? [], $name);
                $fields[$content['machine_name'].'|'.$name] = $content['name']. ': '.  $field['label'] ?? $name;
            }
        }

        return new Response(View::view('default.view.content_views_view_display_controller',
            ['view'=>$view, 'types'=>$types, 'contents'=>$contents, 'fields'=> $fields]));
    }

    public function content_views_view_display_edit_controller(...$args): Response|RedirectResponse
    {
        extract($args);
        $form_base = new FormBuilder(new DisplayEditForm());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.content_views_view_display_edit_view', ['_form'=>$form_base]));
    }

    public function admin_search_settings(...$args): Response
    {
        extract($args);
        $search = SearchManager::searchManager()->getSettings();
        return new Response(View::view('default.view.admin_search_settings',['search_settings'=>$search]));
    }

    public function admin_search_settings_new(...$args): Response
    {
        extract($args);
        $form_base = new FormBuilder(new SearchFormConfiguration());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.admin_search_settings_new', ['_form'=>$form_base]));
    }

    public function admin_search_settings_edit(...$args): Response
    {
        extract($args);
        $form_base = new FormBuilder(new SearchFormConfiguration());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.admin_search_settings_new', ['_form'=>$form_base]));
    }

    public function admin_search_settings_delete(...$args): RedirectResponse
    {
        extract($args);
        $key = $request->get('key');
        if (SearchManager::searchManager()->removeSetting($key)) {
            Messager::toast()->addMessage("Search settings successfully removed.");
            return new RedirectResponse("/admin/search/settings");
        }
        Messager::toast()->addMessage("Search settings was not removed.");
        return new RedirectResponse("/admin/search/settings");
    }

    public function admin_search_settings_configure(...$args): Response|JsonResponse
    {
        extract($args);
        $key = $request->get('key');

        if ($request->getMethod() === 'POST') {
            $data = json_decode($request->getContent(), true);

            if (isset($data['action']) && $data['action'] === 'content_type_new') {
                $content_type = $data['data'] ?? null;
                if ($content_type) {
                    $search_setting = SearchManager::searchManager()->getSetting($key);
                    if ($search_setting) {
                        $content_types = $content_type === 'all' ? array_keys(ContentDefinitionManager::contentDefinitionManager()->getContentTypes()):
                            [$content_type];

                        $search_setting['sources'] = array_merge($search_setting['sources'] ?? [], $content_types);
                        return new JsonResponse(['success' => SearchManager::searchManager()->addSetting($key, $search_setting)]);
                    }
                }
            }

            elseif (isset($data['action']) && $data['action'] === 'content_type_field_new') {
                $content_type = $data['data'] ?? null;
                if ($content_type) {
                    $search_setting = SearchManager::searchManager()->getSetting($key);
                    if ($search_setting) {
                        $search_setting['fields'][] = $content_type;
                        return new JsonResponse(['success' => SearchManager::searchManager()->addSetting($key, $search_setting)]);
                    }
                }
            }

            elseif (isset($data['action']) && $data['action'] === 'filter_type') {
                $value = $data['value'] ?? null;
                $for = $data['data_for'] ?? null;
                if ($for && $value) {
                    $search_setting = SearchManager::searchManager()->getSetting($key);
                    if ($search_setting) {
                        $search_setting['filter_definitions'][$for] = $value;
                        return new JsonResponse(['success' => SearchManager::searchManager()->addSetting($key, $search_setting)]);
                    }
                }
            }

            elseif (isset($data['action']) && $data['action'] === 'exposed') {
                $value = $data['value'] ?? false;
                $search_setting = SearchManager::searchManager()->getSetting($key);
                $search_setting['exposed'][$value] = empty($search_setting['exposed'][$value]);
                return new JsonResponse(['success' => SearchManager::searchManager()->addSetting($key, $search_setting)]);
            }

            elseif (isset($data['action']) && $data['action'] === 'remove') {
                $value = $data['value'] ?? false;
                $search_setting = SearchManager::searchManager()->getSetting($key);

                if ($search_setting) {
                    foreach ($search_setting['fields'] ?? [] as $key => $field) {
                        if ($field === $value) {
                            unset($search_setting['fields'][$key]);
                        }
                    }
                    foreach ($search_setting['filter_definitions'] ?? [] as $key => $field) {
                        if ($key === $value) {
                            unset($search_setting['filter_definitions'][$key]);
                        }
                    }
                    foreach ($search_setting['exposed'] ?? [] as $key => $field) {
                        if ($key === $value) {
                            unset($search_setting['exposed'][$key]);
                        }
                    }
                }
                $key = $request->get('key');
                return new JsonResponse(['success' => SearchManager::searchManager()->addSetting($key, $search_setting)]);
            }
        }

        $search = SearchManager::searchManager()->getSetting($key);
        $columns = [];
        if (!empty($search['type']) && $search['type'] === 'database_type' && !empty($search['sources'])) {

            foreach ($search['sources'] as $source) {
                $columns_old = SearchManager::searchManager()->getDatabaseSearchableColumns($source);
                $columns = array_merge($columns, $columns_old);
            }

        }
        $content_types = array_keys(ContentDefinitionManager::contentDefinitionManager()->getContentTypes());
        $searchable_fields = SearchManager::searchManager()->getSourceSearchableField($key);

        return new Response(View::view('default.view.admin_search_settings_configure', [
            'search_setting'=>$search,
            'key'=>$key,
            'content_types' => $content_types,
            'searchable_fields'=>$searchable_fields,
            'tables' => $searchable_fields,
            'columns'=>$columns
        ]
        ));

    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function admin_search_settings_search_page(...$args): Response|RedirectResponse
    {
        extract($args);
        $search_key = $request->get('key');
        if (empty($search_key)) {
            return new RedirectResponse("/");
        }
        $search_handler = SearchManager::searchManager();
        $search_handler->buildSearchQuery($search_key,$request);
        $search_handler->runQuery($search_key, $request);
        $settings = $search_handler->getSetting($search_key);

        $fields = array_map(function ($field) {
            $list = explode(':', $field);
            return end($list);
        }, $settings['fields']);

        $template = $settings['template'] ?? 'default.view.admin_search_settings_search_page';
        return new Response(View::view($template, ['search_settings'=>$settings, 'fields'=>$fields, 'results'=>$search_handler->getResults()]));
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function integration_configure_rest(...$args): Response|RedirectResponse
    {
        extract($args);
        if ($request->getMethod() === 'POST') {
            $version = $request->request->get('version');
            if (JsonRestManager::factory()->addVersion($version, str_replace(' ', '_', $version))) {
                return new RedirectResponse('/admin/integration/rest');
            }
        }
        $versions = JsonRestManager::factory()->getVersions();
        return new Response(View::view('default.view.integration_configure_rest',['versions'=>$versions]));
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function integration_configure_rest_version(...$args): Response
    {
        extract($args);

        /**@var Request $request**/
        $version = $request->get('version_id');
        $version = JsonRestManager::factory()->getVersion($version);

        if ($request->getMethod() === 'POST' && !$request->request->has('source-data')) {
            $data = [];
            $data['route_type'] = 'rest_'.$version['version_key'];
            $data['title'] = $request->request->get('route_title');
            $data['path'] = "/{$version['version_key']}/". trim($request->request->get('route_path'), '/');
            $data['method']  = [$request->request->get('route_method')];
            $data['access'] = $request->request->all('permission');
            $data['controller'] = [
              'class' => JsonRestController::class,
              'method' => 'handle_api_request',
            ];
            $key = "{$version['version_key']}.".strtolower(str_replace(' ', '.', $data['title']));
            if(JsonRestManager::factory()->addVersionRoute($key, $data)) {
                Messager::toast()->addMessage("Route successfully added.");
                return new RedirectResponse('/admin/integration/rest/version/'.$version['version_key']);
            }
            Messager::toast()->addError("Route was not added.");
            return new RedirectResponse('/admin/integration/rest/version/'.$version['version_key']);
        }

        elseif ($request->getMethod() === 'DELETE') {
            $data = json_decode($request->getContent(), true);
            if (!empty($data['route'])) {
                JsonRestManager::factory()->removeVersionRoute($data['route']);
                return new JsonResponse(['success'=>true]);
            }
        }

        elseif ($request->getMethod() === 'POST' && $request->request->has('source-data') ) {
            $data = $request->request->all();
            $data_providers = JsonRestManager::factory()->getDataProviders();
            if (JsonRestManager::factory()->addVersionRouteDataSourceSetting($data['route'], $data_providers[$data['data_source']]['handler'])) {
                Messager::toast()->addMessage("Route data source successfully added.");
            }else {
                Messager::toast()->addError("Route data source was not added.");
            }
            return new RedirectResponse('/admin/integration/rest/version/'.$version['version_key']);
        }

        $version_routes = JsonRestManager::factory()->getVersionRoute($version['version_key']);
        $data_sources = JsonRestManager::factory()->getDataProviders();
        return new Response(View::view('default.view.integration_configure_rest_version',
            ['version'=>$version,
                'version_routes'=>$version_routes,
                'data_sources'=>$data_sources,
            ])
        );

    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function integration_configure_rest_version_delete(...$args): RedirectResponse
    {
        extract($args);
        $version = $request->get('version_id');
        if (JsonRestManager::factory()->deleteVersion($version)) {
            Messager::toast()->addMessage("Version $version was successfully deleted.");
        }
        return new RedirectResponse('/admin/integration/rest');
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function cron_manage(...$args): Response|RedirectResponse
    {
        \extract($args);

        $cron_manager = Cron::factory();

        $logs = $cron_manager->getCronLogs();
        $schedules = $cron_manager->getScheduledCrons();

        $scripts = $cron_manager->getCronScriptFile();

        return new Response(View::view('default.view.cron_manage',
            [
                'jobs'=> $cron_manager->getCrons(),
                'logs'=>$logs,
                'schedules'=>$schedules,
                'scripts'=>$scripts
            ]
        )
        );
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function cron_add(...$args): Response|RedirectResponse {

        \extract($args);
        $formBase = new FormBuilder(new FormsAddCronForm);
        $formBase->getFormBase()->setFormMethod('POST');
        $formBase->getFormBase()->setFormEnctype('multipart/form-data');



        return new Response(View::view("default.view.cron.add",['_form'=> $formBase]));
    }
}
