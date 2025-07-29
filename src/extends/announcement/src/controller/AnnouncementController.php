<?php

namespace Simp\Core\extends\announcement\src\controller;

use Simp\Core\extends\announcement\src\plugin\Announcement;
use Simp\Core\lib\themes\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class AnnouncementController
{
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function announcement_notifications(...$args): Response
    {
        extract($args);
        $announcements = Announcement::factory()->getAnnouncements();
        return new Response(View::view('default.view.announcement_notifications', ['announcements'=> $announcements]), Response::HTTP_OK);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function announcement_support_window(...$args): Response|RedirectResponse
    {
        return new Response(View::view('default.view.announcement_support_window'), Response::HTTP_OK);
    }
}