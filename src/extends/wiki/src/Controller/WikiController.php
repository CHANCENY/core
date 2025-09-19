<?php

namespace Simp\Core\extends\wiki\src\Controller;

use DI\DependencyException;
use DI\NotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\wiki\src\Entity\Wiki;
use Simp\Core\extends\wiki\src\enum\WikiStatusEnum;
use Simp\Core\extends\wiki\src\Form\WikiCreateForm;
use Simp\Core\extends\wiki\src\Form\WikiTagForm;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\taxonomy\Term;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class WikiController
{
    protected int $limit = 10;
    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function entries(...$args): Response
    {
        ModuleHandler::factory()->attachLibrary('wiki', 'wiki.library');
        $wiki_tags = Term::factory()->getTermByVid('wiki');
        return new Response(View::view('default.view.wiki.entries',[
            'tags'=>$wiki_tags,
        ]));
    }

    /**
     * @param mixed ...$args
     * @return JsonResponse
     * @throws DependencyException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws \DateMalformedStringException
     */
    public function tag_entries(...$args): JsonResponse
    {
        extract($args);
        $tid = $request->get('id',null);
        $page = $request->get('page',1);
        $q = $request->get('q',null);

        if (empty($tid)) {
            return new JsonResponse(['error'=>'Invalid tag id'],400);
        }

        $offset = ($page - 1) * $this->limit;
        $wikis = [];

        if (!empty($q)) {
            $wikis = Wiki::loadBySearch(['tid'=>$tid,'q'=>$q,'limit'=>$this->limit,'offset'=>$offset]);
        }
        else {
            $wikis = Wiki::loadByTag($tid,$this->limit,$offset);
        }

        $wiki_list = [];
        foreach ($wikis as $wiki) {
            $wiki_list[] = [
                'title' => $wiki->getTitle(),
                'id' => $wiki->id(),
                'summary' => $wiki->getSummary(300),
                'authors' => array_map(function($author) {
                    return [
                        'name' => $author->getName(),
                        'role' => implode(',', array_map(function($role) { return $role->getRoleLabel(); },$author->getRoles()))
                    ];
                },$wiki->getAuthorsList()),
                'slug' => $wiki->getSlug(),
            ];
        }

        return new JsonResponse([
            'entries' => $wiki_list,
            'hasMore' => true
        ]);
    }

    public function search(...$args)
    {
        extract($args);
        $q = $request->get('q',null);
        $page = $request->get('page',1);
        $tid = $request->get('id',null);

        $offset = ($page - 1) * $this->limit;

        if (empty($q)) {
            return new JsonResponse(['error'=>'Invalid search query'],400);
        }

        $search_params = [];
        if (!empty($tid)) {
            $search_params['tid'] = $tid;
        }
        $search_params['q'] = $q;
        $search_params['limit'] = $this->limit;
        $search_params['offset'] = $offset;

        $wikis = Wiki::loadBySearch($search_params);

        $wiki_list = [];
        foreach ($wikis as $wiki) {
            $wiki_list[] = [
                'title' => $wiki->getTitle(),
                'id' => $wiki->id(),
                'summary' => $wiki->getSummary(300),
                'authors' => array_map(function($author) {
                    return [
                        'name' => $author->getName(),
                        'role' => implode(',', array_map(function($role) { return $role->getRoleLabel(); },$author->getRoles()))
                    ];
                },$wiki->getAuthorsList()),
                'slug' => $wiki->getSlug(),
            ];
        }

        return new JsonResponse([
            'entries' => $wiki_list,
            'hasMore' => true
        ]);
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     * @throws \DateMalformedStringException
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws NotFoundException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function entry(...$args): Response
    {
        extract($args);

        ModuleHandler::factory()->attachLibrary('wiki', 'wiki.editor');

        $slug = $request->get('slug', null);

        if (empty($slug)) {
            return new Response("Invalid slug", 400);
        }

        $wiki = Wiki::loadBySlug($slug);

        if (empty($wiki)) {
            return new Response("Wiki not found", 404);
        }

        $authors = [];

        foreach ($wiki->getAuthorsList() as $author) {
            $full_name = "";
            $profile = $author->getProfile();
            if (!empty($profile->getFirstName())) {
                $full_name .= $profile->getFirstName();
            }
            if (!empty($profile->getLastName())) {
                $full_name .= " " . $profile->getLastName();
            }
            $authors[] = [
                'name' => !empty($full_name) ? $full_name :  $author->getName(),
                'role' => implode(',', array_map(function($role) { return $role->getRoleLabel(); },$author->getRoles())),
                'avatar_url' => !empty($profile->getImage()) ? $profile->getImage() : "/core/modules/wiki/assets/User-Avatar-Profile-PNG-Pic.png",
                'id' => $author->id(),
                'bio' => $profile->getDescription()
            ];
        }

        $related = $wiki->getRelatedWiki(4);

        $editable = false;

        $allowed = array(
            'administrator',
            'content_creator',
            'manager',
            'editor',
            'author',
            'subscriber',
            'contributor',
            'moderator',
            'reviewer',
            'publisher',
            'analyst',
            'support',
        );

        $user = CurrentUser::currentUser()->getUser();
        foreach ($allowed as $role) {
            if ($user->roleManager()->isRoleExist($role)) {
                $editable = true;
                break;
            }
        }

        return new Response(View::view('default.view.wiki.content',[
            'wiki' => $wiki,
            'authors' => $authors,
            'related' => $related,
            'page_title' => $wiki->getTitle() . ' - Wiki',
            'noscript' => [
                'wiki_id' => $wiki->id(),
                'wiki_wrapper' => 'wiki-wrapper',
                'editable' => $editable,
                'author' => CurrentUser::currentUser()->getUser()->id()
            ]
        ]));
    }

    /**
     * @throws DependencyException
     * @throws \DateMalformedStringException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function revision_add(...$args): Response
    {
        extract($args);

        $wiki_data = json_decode($request->getContent(),true);

        if (!empty($wiki_data['content']) && !empty($wiki_data['author']) && !empty($wiki_data['status']) && !empty($wiki_data['wiki_id'])) {

            $wiki = Wiki::load($wiki_data['wiki_id']);
            $status = strtolower($wiki_data['status']);
            $status = WikiStatusEnum::tryFrom($status);
            if (empty($status)) {
                return new JsonResponse(['error'=>'Invalid status'],400);
            }

            $wiki->addRevision($wiki_data['content'],$status, intval($wiki_data['author']));

            return new JsonResponse(['success'=>true]);

        }

        return new JsonResponse(['error'=>'Invalid request'],400);

    }

    public function create(...$args)
    {
        extract($args);
        $wiki_form = new FormBuilder(new WikiCreateForm(['request'=>$request]));
        $wiki_form->getFormBase()->setFormMethod('POST');

        return new Response(View::view('default.view.wiki.creation.form',['_form'=>$wiki_form]));
    }

    public function tag_create(...$args): Response
    {
        extract($args);

        $wiki_tag_form = new FormBuilder(new WikiTagForm(['request'=>$request]));
        $wiki_tag_form->getFormBase()->setFormMethod('POST');
        return new Response(View::view('default.view.wiki.tag.form',['_form'=>$wiki_tag_form]));
    }

}