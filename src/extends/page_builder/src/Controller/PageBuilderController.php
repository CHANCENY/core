<?php

namespace Simp\Core\extends\page_builder\src\Controller;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\extends\page_builder\src\CreatePage;
use Simp\Core\extends\page_builder\src\Plugin\Page;
use Simp\Core\extends\page_builder\src\Plugin\PageConfigManager;
use Simp\Core\lib\themes\View;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PageBuilderController
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
    public function create(...$args): RedirectResponse|Response
    {
        extract($args);

        $t = $request->get('t', null);
        if ($t) {
            return new RedirectResponse("/core/modules/page_builder/build?t={$t}");
        }
        $form_base = new FormBuilder(new CreatePage());
        $form_base->getFormBase()->setFormMethod('POST');
        $form_base->getFormBase()->setFormEnctype('multipart/form-data');

        return new Response(View::view('default.view.page_builder.create',['__form'=>$form_base]), Response::HTTP_OK);
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
    public function dashboard(...$args): Response
    {
        extract($args);
        $pages = PageConfigManager::factory()->getPages();
        return new Response(View::view('default.view.page_builder.dashboard', [
            'pages' => $pages,
        ]));
    }

    public function save(...$args): JsonResponse
    {
        extract($args);
        $json_form = json_decode($request->getContent(), true);

        if (!empty($json_form['name']) && !empty($json_form['html']) && !empty($json_form['css']))
        {
            $page_config = PageConfigManager::factory($json_form['name'])->addPage($json_form['name'], $json_form['name'], $json_form['css'], $json_form['html']);
            return new JsonResponse(['success' => true, 'message' => 'Page saved successfully', 'name' => $page_config]);
        }
        return new JsonResponse(['success' => false, 'message' => 'failed to save page']);
    }

    public function search(...$args)
    {
        extract($args);
        $search = $request->get('name');

        $results = Page::search($search);
        $results = array_map(function ($result) {
            sleep(1);
            return [
                'pid' => $result['id'],
                'active' => $result['status'],
                ...$result,
            ];
        }, $results);
        return new JsonResponse($results);
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
    public function embeddable(...$args): Response
    {
        extract($args);
        $pid = $request->get('pid', null);

        if (!$pid) {
            return new Response("<h1>Content not found</h1>");
        }

        $page = Page::load($pid);
        return new Response(View::view('default.view.page_builder.embeddable',[
            'content' => $page->getContent(),
            'css' => $page->getCss(),
            'title' => $page->getTitle(),
            'pid' => $pid,
            'active' => $page->getStatus(),
            'version' => $page->getVersion(),
            'name' => $page->getName(),
        ]));
    }

    public function link(...$args)
    {
        extract($args);
        $pid = $request->get('pid', null);
        $name = $request->get('name', null);

        if (!empty($name)) {
            $name = $this->removeSpecialCharacters($name);
        }

        if (!$pid) {
            return new RedirectResponse($request->headers->get('referer') ?? '/');
        }

        $page = Page::load($pid);
        return new Response(View::view('default.view.page_builder.link',[
            'content' => $page->getContent(),
            'css' => $page->getCss(),
            'title' => $name,
            'pid' => $pid,
            'active' => $page->getStatus(),
            'version' => $page->getVersion(),
            'name' => $page->getName(),
            'css_wrapper' => uniqid("page-content-"),
            'page_title' => $name
        ]));
    }

    protected function removeSpecialCharacters(string $text): string {
        // Replace anything that is not a letter, number, or space with a space
        $text = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text);

        // Replace multiple spaces with a single space
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim leading/trailing spaces
        return trim($text);
    }

}