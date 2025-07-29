<?php

namespace Simp\Core\extends\image\src\controller;

use Simp\Core\extends\image\src\Loader\Gallery;
use Symfony\Component\HttpFoundation\JsonResponse;

class ImageController
{
    public function loader(...$args): JsonResponse
    {
        extract($args);
        $page = $request->get("page", 0);
        $gallery = Gallery::factory()->getImagesByPage($page,  5);
        return new JsonResponse(['results' => $gallery, 'success' => true]);
    }

    public function upload(...$args): JsonResponse
    {
        return new JsonResponse(['success' => true, 'message' => 'coming soon']);
    }
}