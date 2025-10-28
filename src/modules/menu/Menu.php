<?php

namespace Simp\Core\modules\menu;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\services\Service;
use Symfony\Component\HttpFoundation\Request;

class Menu
{
    private ?Route $menu;

    /** @var Menu[] */
    protected array $children = [];

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct(array|string $menu) {

        if (is_array($menu)) {
            $this->menu = new Route(...$menu);
        }
        else {
            $this->menu = Route::fromRouteName($menu);
        }
    }

    public function addChild(Menu $child): void
    {
        $this->children[] = $child;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function getMenu(): ?Route
    {
        return $this->menu;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setAccess(array $access)
    {
        $this->menu->access = $access;
    }

    public function isActive()
    {
        /**@var \Simp\Core\lib\routes\Route $route */
        $route = Request::createFromGlobals()->server->get('ROUTE_ATTRIBUTES')['route'];

        return $route->route_id === $this->menu->route_id;
    }

}