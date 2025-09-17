<?php

namespace Simp\Core\lib\controllers;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\lib\forms\ExtendAddFrom;
use Simp\Core\lib\routes\Route;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\messager\Messager;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ExtendController
{

    /**
     * @param mixed ...$args
     * @return Response|RedirectResponse
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function extend_manage(...$args): Response|RedirectResponse
    {
        \extract($args);
        if ($request->getMethod() === 'POST' && $request->request->has('enabled_module')) {
            $modules = $request->request->all();
        
            foreach($modules['module'] as $module) {
                ModuleHandler::factory()->moduleEnable($module);
            }
            Messager::toast()->addMessage("Updates save successfully.");
            return new RedirectResponse('/admin/extends');
        }
        if ($request->getMethod() === 'POST' && $request->request->has('unabled_module')) {
            $modules = $request->request->all();

            foreach($modules['module'] as $module) {
                ModuleHandler::factory()->moduleDisable($module);
            }
            Messager::toast()->addMessage("Updates save successfully.");
            return new RedirectResponse('/admin/extends');
        }

        $modules = ModuleHandler::factory()->getModules();
        $moduler = ModuleHandler::factory();
        $enabled_modules = [];
        $un_enabled_modules = [];
        foreach ($modules as $key=>&$module) {
            $module['enabled'] = $moduler->isModuleEnabled($key);
        }
        return new Response(View::view('default.view.extend_manage',[
            'lists' => $modules,
        ]), Response::HTTP_OK);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function extend_manage_add(...$args): Response|RedirectResponse
    {
        $form_base = new FormBuilder(new ExtendAddFrom());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');
        return new Response(View::view('default.view.extend_manage_add',['_form'=>$form_base]), Response::HTTP_OK);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function extend_manage_enable(...$args): RedirectResponse
    {
        extract($args);
        $module = $request->get('name');
        ModuleHandler::factory()->moduleEnable($module);
        Messager::toast()->addMessage("Updates save successfully.");
        return new RedirectResponse(Route::url('system.extends.manage'));
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function extend_manage_disabled(...$args): RedirectResponse
    {
        extract($args);
        $module = $request->get('name');
        ModuleHandler::factory()->moduleDisable($module);
        Messager::toast()->addMessage("Updates save successfully.");
        return new RedirectResponse(Route::url('system.extends.manage'));
    }
}
