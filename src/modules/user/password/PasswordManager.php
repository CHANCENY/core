<?php

namespace Simp\Core\modules\user\password;

use Exception;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Random\RandomException;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\mail\MailManager;
use Simp\Core\modules\user\entity\User;
use Simp\Mail\Mail\Envelope;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class PasswordManager
{
    protected User $user;


    /**
     * @throws Exception
     */
    public function __construct(int $uid)
    {
        $user = User::load($uid);
        if ($user === null) {
            throw new Exception("User not found");
        }
        $this->user = $user;
    }

    public function changePassword(string $new_password): bool
    {
        $this->user->setPassword(password_hash($new_password, PASSWORD_BCRYPT));
        return $this->user->update();
    }

    /**
     * @throws RandomException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function forgotPasswordLink(): string
    {
        $random_bytes = random_bytes(32);
        $random_hash = bin2hex($random_bytes);
        $random_hash = hash('sha256', $random_hash);
        Caching::init()->set($random_hash, $this->user);
        return "/user/password/reset/{$random_hash}";
    }

    /**
     * @throws RandomException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function oneTimeLogin(): string
    {
        $random_bytes = random_bytes(32);
        $random_hash = bin2hex($random_bytes);
        $random_hash = hash('sha256', $random_hash);
        Caching::init()->set($random_hash, $this->user);
        return "/user/login/link/{$random_hash}";
    }

    /**
     * @throws RandomException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function sendForgotPasswordLink(): bool|array|null
    {
        $host = Service::serviceManager()->request->getSchemeAndHttpHost() . $this->forgotPasswordLink();
        $mail = MailManager::mailManager();

        $mail->addEnvelope(Envelope::create(
            'Forgot Password One Time link',
            nl2br("You have requested to reset your password here is your one-time forgot password link to reset your password \n \n
                $host \n \n please ignore this email if you don't really want to reset your password."),
        )->addToAddresses([
            [
                'name' => $this->user->getProfile()->getFirstName() ?? $this->user->getName(),
                'value' => $this->user->getMail()
            ]
        ]));
        return $mail->send();
    }
}