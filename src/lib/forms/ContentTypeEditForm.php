<?php

namespace Simp\Core\lib\forms;

use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class ContentTypeEditForm extends ContentTypeForm
{
    public function submitForm(array $form): void
    {
        $request = Service::serviceManager()->request;
        $new_data = $request->request->all();
        $name = $request->get('machine_name');
        $content = ContentDefinitionManager::contentDefinitionManager()->getContentType($name);
        $content = array_merge($content, $new_data);

        ContentDefinitionManager::contentDefinitionManager()->addContentType($name, $content);
        $redirect = new RedirectResponse('/admin/structure/types');
        $redirect->setStatusCode(302);
        Messager::toast()->addMessage("Content type \"$name\" successfully updated.");
        $redirect->send();
    }
}