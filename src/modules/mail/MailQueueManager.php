<?php

namespace Simp\Core\modules\mail;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\memory\session\Session;
use Simp\Mail\Mail\Envelope;

class MailQueueManager
{
    protected array $queue = [];
    protected Session $caching;

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $this->caching = Session::init();
        $this->queue = $this->caching->get('system_mail_queue') ?? [];
    }

    /**
     * Adds an envelope to the desired collection or process.
     *
     * @param Envelope $envelope The envelope to be added.
     * @return void
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function add(Envelope $envelope): void
    {
        $this->queue[] = $envelope;
        $this->caching->set('system_mail_queue', $this->queue);
    }

    /**
     * Adds an array of envelopes to the mail queue.
     *
     * @param array<Envelope> $envelopes An array of envelopes to be added to the queue.
     * @return void
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function addArray(array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            if ($envelope instanceof Envelope) {
                $this->add($envelope);
            }
        }
    }

    /**
     * Sends all envelopes in the queue using the MailManager.
     *
     * This method processes each envelope in the queue by adding it to the
     * MailManager and sending it. After sending all envelopes, the queue is
     * cleared and the global mail queue is updated to reflect the changes.
     *
     * @return void
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheIOException
     */
    public function send(): void
    {
        $this->caching->delete('system_mail_queue');
        foreach ($this->queue as $envelope) {
           if ($envelope instanceof Envelope) {
               MailManager::mailManager()->addEnvelope($envelope)->send();
           }
        }
    }

    /**
     * Creates and returns a new instance of MailQueueManager.
     *
     * This method functions as a factory for creating instances of the
     * MailQueueManager class.
     *
     * @return MailQueueManager A new instance of the MailQueueManager class.
     */
    public static function factory(): MailQueueManager
    {
        return new self();
    }
}