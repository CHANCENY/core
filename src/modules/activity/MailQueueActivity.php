<?php

namespace Simp\Core\modules\activity;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\event_subscriber\EventSubscriber;
use Simp\Core\modules\mail\MailQueueManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MailQueueActivity implements EventSubscriber
{

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function listeners(Request $request, Route $route, ?Response $response): void
    {
        MailQueueManager::factory()->send();

    }
}