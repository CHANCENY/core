<?php


use Simp\Core\modules\auth\normal_auth\AuthUser;
use Twig\TwigFilter;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\config\config\ConfigReadOnly;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Translate\translate\Translate;

/**
 * @throws PhpfastcacheCoreException
 * @throws PhpfastcacheLogicException
 * @throws PhpfastcacheDriverException
 * @throws PhpfastcacheInvalidArgumentException
 */
function filter_translator(string $text, ?string $from = null, ?string $to = null): Translate|string
{
    // Check if the current user has timezone translation enabled.
    $current_user = CurrentUser::currentUser();

    if ($current_user instanceof AuthUser && !$current_user->getUser()->getProfile()->isTranslationEnabled()) {
        return $text;
    }

    if ($to === null || $to === '' || $to === '0') {
        if ($current_user?->getUser()?->getProfile()?->isTranslationEnabled() === true) {
            $to = $current_user?->getUser()?->getProfile()?->getTranslation();
        }else {
            $to = 'en';
        }
    }

    // Get the system language.
    if ($from === null || $from === '' || $from === '0') {
        $config = ConfigManager::config()->getConfigFile('system.translation.settings');
        $from = $config instanceof ConfigReadOnly ? $config->get('system_lang', 'en') : 'en';
    }

    if (is_dir('public://translations')) {
        @mkdir('public://translations');
    }

    return Translate::translate($text,$from, $to, 'public://translations');
}

function get_filters(): array
{
    return [
        new TwigFilter('t',fn(string $text, ?string $from = null, ?string $to = null): \Simp\Translate\translate\Translate|string => filter_translator($text, $from, $to)),
        new TwigFilter('class',function ($object): ?string {
            if (is_object($object)) {
                return $object::class;
            }

            return null;
        })
    ];
}