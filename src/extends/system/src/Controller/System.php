<?php

namespace Simp\Core\extends\system\src\Controller;



use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\request\Request;
use Simp\Core\extends\system\src\Plugin\SystemAction;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\auth\AuthenticationSystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class System
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
    public function system(...$args): Response
    {
        return new Response(View::view('default.view.system.module.dashboard'));
    }

    public function system_rebuild(...$args): RedirectResponse
    {
        extract($args);

        SystemAction::copyInstallers();
        SystemAction::rebuildCore();
        SystemAction::moveModules();
        /**@var Request $request**/
        return new RedirectResponse($request->headers->get('referer') ?? '/');
    }

    public function rebuild_cache(...$args): RedirectResponse
    {
        extract($args);
        /**@var Request $request**/
        SystemAction::rebuildCache();
        return new RedirectResponse($request->headers->get('referer') ?? '/');
    }

    public function clear_cache(...$args): RedirectResponse
    {
        extract($args);
        SystemAction::clearCache();
        return new RedirectResponse($request->headers->get('referer') ?? '/');
    }

    public function rebuild_all(...$args): RedirectResponse
    {
        extract($args);
        SystemAction::rebuildAll();
        return new RedirectResponse($request->headers->get('referer') ?? '/');
    }

    public function content_types(...$args): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        extract($args);
        SystemAction::persistContentTypes();
        return new RedirectResponse($request->headers->get('referer') ?? '/');
    }

    public function outh_setting(...$args)
    {
        extract($args);

        /** @var Request $request **/

        $authentication = AuthenticationSystem::getSetting();

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();

            $authentication = [
                'normal' => [
                    'types' => ['password', 'password-less'],
                    'default' => $data['normal_type'] ?? 'password',
                ],
                'google' => [
                    'types' => ['google'],
                    'default' => $data['active'] === 'google' ? 'google' : null,
                    'credential' => [
                        'client_id' => $data['google_client_id'] ?? null,
                        'client_secret' => $data['google_client_secret'] ?? null,
                        'redirect' => $data['google_redirect'] ?? '/user/oauth/google/access',
                        'scope' => $data['google_scope'] ?? ['email', 'profile'],
                    ],
                ],
                'github' => [
                    'types' => ['github'],
                    'default' => $data['active'] === 'github' ? 'github' : null,
                    'credential' => [
                        'client_id' => $data['github_client_id'] ?? null,
                        'client_secret' => $data['github_client_secret'] ?? null,
                        'redirect' => $data['github_redirect'] ?? '/user/oauth/github/access',
                    ],
                ],
                'active' => $data['active'] ?? 'normal',
            ];

            AuthenticationSystem::addSetting($authentication);
            return new RedirectResponse($request->headers->get('referer') ?? '/');
        }

        return new Response(View::view('default.view.system.outh.setting', [
            'authentication' => $authentication
        ]));
    }


}