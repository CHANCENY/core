<?php

namespace Simp\Core\extends\system\src\Controller;



use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\request\Request;
use Simp\Core\extends\system\src\Plugin\SystemAction;
use Simp\Core\lib\themes\View;
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

    public function content_types(...$args)
    {
        extract($args);
        SystemAction::persistContentTypes();
        return new RedirectResponse($request->headers->get('referer') ?? '/');
    }

}