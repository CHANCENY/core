<?php

/**
 * Interface EventSubscriber
 *
 * Represents a contract for implementing event subscriber classes
 * that listen to and respond to application events. Classes implementing
 * this interface define their event handling logic using the `listeners` method.
 */

namespace Simp\Core\modules\event_subscriber;

use Simp\Core\lib\routes\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface EventSubscriber
 *
 * Defines a structure for creating event subscribers.
 * Classes implementing this interface must provide a mechanism
 * to handle and respond to specific application events by implementing
 * the `listeners` method.
 */
interface EventSubscriber
{
    /**
     * Handles the event listeners for the specified request, route, and response.
     *
     * @param Request $request The incoming HTTP request.
     * @param Route $route The route associated with the request.
     * @param Response|null $response The HTTP response to be sent.
     * @return void
     */
    public function listeners(Request $request, Route $route, ?Response $response): void;
}