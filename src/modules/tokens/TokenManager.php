<?php

namespace Simp\Core\modules\tokens;

use Exception;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\tokens\resolver\ResolverInterface;
use Symfony\Component\Yaml\Yaml;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TokenManager
{
    protected array $tokens = [];
    protected array $resolvers = [];

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $default = Caching::init()->get('default.admin.system_replacement_tokens');
        $resolver = Caching::init()->get('default.admin.system_replacement_token_handlers');
        if ($default && file_exists($default)) {
            $this->tokens = Yaml::parseFile($default);
        }

        if ($resolver && file_exists($resolver)) {
            $this->resolvers = Yaml::parseFile($resolver);
        }

        $system = new SystemDirectory();
        $custom_replacement_token_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'replacement_tokens';
        $custom_resolver_token_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'replacement_token_resolvers';
        if (!is_dir($custom_replacement_token_file)) {
            mkdir($custom_replacement_token_file, 0777, true);
        }
        if (!is_dir($custom_resolver_token_file)) {
            mkdir($custom_resolver_token_file, 0777, true);
        }
        $custom_replacement_token_file = $custom_replacement_token_file . DIRECTORY_SEPARATOR . 'custom_replacement_tokens.yml';
        $custom_resolver_token_file = $custom_resolver_token_file . DIRECTORY_SEPARATOR . 'custom_replacement_token_resolvers.yml';

        if (!file_exists($custom_replacement_token_file)) {
           touch($custom_replacement_token_file);
        }
        if (!file_exists($custom_resolver_token_file)) {
            touch($custom_resolver_token_file);
        }
        $custom = Yaml::parseFile($custom_replacement_token_file);
        $custom_resolvers = Yaml::parseFile($custom_resolver_token_file);
        if ($custom) {
            $this->tokens = array_merge($this->tokens, $custom);
        }
        if ($custom_resolvers) {
            $this->resolvers = array_merge($this->resolvers, $custom_resolvers);
        }
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getFloatingWindow(): string
    {
        return View::view('default.view.replacement_tokens.floating_window', ['tokens' => $this->tokens]);
    }

    /**
     * @throws Exception
     */
    public function resolver(string $text, array $data): string {

        $replaced = $text;
        foreach ($this->tokens as $key => $value) {
            $resolver = $this->resolvers[$key] ?? null;
            if ($resolver) {
                $resolver = new $resolver();
                if (!$resolver instanceof ResolverInterface) {
                    throw new Exception("Token Resolver ({$this->resolvers[$key]}) must implement ".ResolverInterface::class);
                }
                else {
                    $value = $resolver->resolver($key, $value, $data);
                    if ($value) {
                        foreach ($value as $k => $v) {
                            if (str_contains($k, ':')) {
                                $replaced = str_replace("[$k]", $v, $replaced);
                            }
                            else {
                                $replaced = str_replace("[{$key}:{$k}]", $v, $replaced);
                            }

                        }
                    }

                }
            }
        }

        return $replaced;
    }

    public static function token(): TokenManager
    {
        return new self();
    }
}