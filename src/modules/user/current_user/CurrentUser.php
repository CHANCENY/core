<?php

namespace Simp\Core\modules\user\current_user;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\memory\session\Session;
use Simp\Core\modules\auth\normal_auth\AuthUser;
use Simp\Core\modules\user\entity\User;

class CurrentUser
{
    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function currentUser(): ?AuthUser
    {
        return Session::init()->get('private.current.user') ?? AuthUser::auth();
    }
}