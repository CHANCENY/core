<?php

namespace Simp\Core\modules\messager;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\memory\session\Session;
use Simp\Core\modules\user\current_user\CurrentUser;

class Messager
{
    protected array $messages = [];

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $this->messages = Session::init()->get('system.messages') ?? [];
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function addMessage(string $message): void
    {
        $this->messages[] = [
            'message' => $message,
            'type' => 'toastify-success',
            'time' => 3000
        ];
        $this->persist();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function addError(string $message): void
    {
        $this->messages[] = [
            'message' => $message,
            'type' => 'toastify-error',
            'time' => 3000
        ];
        $this->persist();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function addInfo(string $message): void {
        $this->messages[] = [
            'message' => $message,
            'type' => 'toastify-info',
            'time' => 3000
        ];
        $this->persist();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function addWarning(string $message): void
    {
        $this->messages[] = [
            'message' => $message,
            'type' => 'toastify-warning',
            'time' => 3000
        ];
        $this->persist();
    }

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function persist(): void
    {
        Session::init()->set('system.messages', $this->messages);
    }
    public static function toast(): Messager
    {
       return new self();
    }
}