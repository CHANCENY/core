<?php

namespace Simp\Core\modules\tokens\resolver;

use Random\RandomException;
use Simp\Core\components\site\SiteManager;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\user\entity\User;
use Simp\Core\modules\user\profiles\Profile;
use Simp\Core\modules\user\roles\Role;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class DefaultTokenResolver implements ResolverInterface
{

    protected Request $request;
    public function __construct()
    {
        $this->request = Service::serviceManager()->request;
    }

    /**
     * @throws RandomException
     */
    public function resolver(string $type, array $tokens, array $options): array
    {
        $replacements = [];

        if ($type === 'user') {

            foreach ($tokens as $token) {

                $user = $options['user'] ?? null;

                if ($user instanceof User) {
                    $replacements[$token] = match ($token) {
                        'uid' => $user->getUid(),
                        'name' => $user->getName(),
                        'email', 'mail' => $user->getMail(),
                        'created' => $user->getCreated(),
                        'updated' => $user->getUpdated(),
                        'login' => $user->getLogin(),
                        'verify_token' => $user->getVerifyEmailToken(),
                        default => null,
                    };
                }


            }

        }

        elseif ($type === 'site') {

            foreach ($tokens as $token) {

                $site = $options['site'] ?? null;
                if ($site instanceof SiteManager) {

                    $replacements[$token] = match ($token) {
                      'name', 'title', 'brand_name' => $site->get('site_name','Simp CMS'),
                      'slogan', 'description' => $site->get('site_slogan'),
                      'email', 'mail' => $site->get('site_email'),
                      'front_url' => $site->get('front_page_url'),
                      'url' => $this->request->getSchemeAndHttpHost(),
                       default => null,
                    };
                }
            }

        }

        elseif ($type === 'profile') {

            foreach ($tokens as $token) {
                $data_tok = $options['profile'] ?? $options['user'] ?? null;
                $profile = null;
                if ($data_tok instanceof Profile) {
                    $profile = $data_tok;
                }
                elseif ($data_tok instanceof User) {
                    $profile = $data_tok->getProfile();
                }

                if ($profile instanceof Profile) {
                    $replacements[$token] = match ($token) {
                      'first_name' => $profile->getFirstName(),
                      'last_name' => $profile->getLastName(),
                      'description' => $profile->getDescription(),
                      'time_zone' => $profile->getTimeZone(),
                      'translation_code' => $profile->getTranslationCode(),
                      'image_fid' => $profile->getProfileImage(),
                      'image_uri'=> $profile->getImage(),
                        default => null,
                    };
                }
            }

        }

        elseif ($type === 'role') {

            foreach ($tokens as $token) {

                $role = $options['role'] ?? null;
                if ($role instanceof Role) {
                    $replacements[$token] = match ($token) {
                      'name' => $role->getName(),
                      'label' => $role->getRoleLabel(),
                        default => null,
                    };
                }

            }

        }

        elseif ($type === 'file') {

            foreach ($tokens as $token) {
                $file = $options['file'] ?? null;
                if ($file instanceof File) {
                    $replacements[$token] = match ($token) {
                      'fid' => $file->getFid(),
                       'uri' => $file->getUri(),
                       'name' => $file->getName(),
                       'extension' => $file->getExtension(),
                       'type' => $file->getMimeType(),
                       'size' => $file->getSize(),
                       'uploaded' => $file->getCreated(),
                       default => null,
                    };
                }
            }

        }

        elseif ($type === 'request') {

            foreach ($tokens as $token) {

                if (str_contains($token,'get_value?')) {
                   $params = $this->request->attributes->all();
                   $params = array_merge($params, $this->request->query->all());
                   foreach ($params as $k => $value) {
                       $replacements["{$type}:get_value:{$k}"] = is_array($value) ? json_encode($value) : $value;
                   }
                }

                elseif (str_contains($token,'post_value?')) {
                   $payload = $this->request->request->all();
                   foreach ($payload as $k => $value) {
                       $replacements["{$type}:post_value:{$k}"] = is_array($value) ? json_encode($value) : $value;
                   }
                }

                else {
                    $replacements[$token] = match ($token) {
                        'uri', 'current_uri' => $this->request->getRequestUri(),
                        'method' => $this->request->getMethod(),
                        'scheme' => $this->request->getScheme(),
                        'host' => $this->request->getHost(),
                        'port' => $this->request->getPort(),
                        'path' => $this->request->getPathInfo(),
                        'query' => $this->request->getQueryString(),
                        'ip' => $this->request->getClientIp(),
                        'user_agent' => $this->request->headers->get('User-Agent'),
                        'current_url' => $this->request->getSchemeAndHttpHost() . $this->request->getRequestUri(),
                        'origin' => $this->request->headers->get('Origin'),
                        'referer' => $this->request->headers->get('Referer'),
                        'is_secure' => $this->request->isSecure(),
                        'is_ajax' => $this->request->isXmlHttpRequest(),
                        'hostname' => $this->request->getHttpHost(),
                        'base' => $this->request->getSchemeAndHttpHost(),
                        default => null,
                    };
                }

            }
        }

        elseif ($type === 'node') {

            foreach ($tokens as $token) {


                $node = $options['node'] ?? null;
                if ($node instanceof Node) {

                    if (str_contains($token,'field?')) {
                        $list = explode(':', $token);
                        $field = end($list);
                        $values = $node->get($field);
                        $value = "";
                        if (is_array($values)) {
                            $value = json_encode($values, JSON_PRETTY_PRINT);
                        }
                        else {
                            $value = $values;
                        }
                        $replacements[$token] = $value;
                    }

                    $replacements[$token] = match ($token) {
                        'nid' => $node->getNid(),
                        'title' => $node->getTitle(),
                        'created' => $node->getCreated(),
                        'updated' => $node->getUpdated(),
                        'status' => $node->getStatus(),
                        'language' => $node->getLang(),
                        default => null,
                    };
                }
            }

        }

        return $replacements;
    }
}