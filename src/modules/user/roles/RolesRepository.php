<?php

namespace Simp\Core\modules\user\roles;


use DI\DependencyException;
use DI\NotFoundException;
use Simp\Core\extends\multi_site_support\src\Plugin\MultiSiteSupport;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\services\Service;
use Symfony\Component\Yaml\Yaml;

class RolesRepository
{
    protected array $primaryRoles;

    protected array $secondaryRoles;

    /**
     * Constructor.
     */
    public function __construct(SystemDirectory $systemDirectory)
    {
        $system_role_file = $systemDirectory->webroot_dir . DIRECTORY_SEPARATOR . 'core' .
            DIRECTORY_SEPARATOR . 'defaults' . DIRECTORY_SEPARATOR . 'roles' .
            DIRECTORY_SEPARATOR . 'system.roles.yml';

        if (file_exists($system_role_file)) {
            $roles = Yaml::parse(file_get_contents($system_role_file));
            $this->primaryRoles = $roles['roles']['primary'] ?? [];
            $this->secondaryRoles = $roles['roles']['secondary'] ?? [];
            $this->primaryRoles = array_map([$this, 'normalizeRole'], $this->primaryRoles);
            $this->secondaryRoles = array_map([$this, 'normalizeRole'], $this->secondaryRoles);
        }

        if (MultiSiteSupport::isMultiSiteSupportEnabled()) {
            $request = Service::get('request');
            $domain = $request->getHttpHost();

            /**@var MultiSiteSupport $multi_site_support */
            $multi_site_support = Service::get(MultiSiteSupport::class);

            $site = $multi_site_support->getSiteByDomain($domain);

            if (!empty($site))
            {
                $primary_roles = $site['primaryRole'] ?? 'authenticated';
                $secondary_roles = $site['additionalRoles'] ?? [];

                if ($multi_site_support->getThemeIdByDomain($domain) !== 'default') {
                    $this->primaryRoles = [$primary_roles];
                    $this->secondaryRoles = $secondary_roles;
                }
            }

        }
    }

    /**
     * Normalizes a role name by removing special characters and converting to lowercase.
     */
    protected function normalizeRole(string $role): string
    {
        // lower case and remove special characters and spaces replace with _ if __ replace with _
        return strtolower((string) preg_replace('/[^a-zA-Z0-9_]/', '_', $role));
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public static function factory(): RolesRepository
    {
        return new self(Service::get('system.directory'));
    }

    /**
     * Retrieves the primary roles.
     *
     * @return array The list of primary roles.
     */
    public function getPrimaryRoles(): array {
        return $this->primaryRoles;
    }

    /**
     * Retrieves the secondary roles associated with the current instance.
     *
     * @return array The array of secondary roles.
     */
    public function getSecondaryRoles(): array {
        return $this->secondaryRoles;
    }

    /**
     * Retrieves a list of roles by merging primary and secondary roles.
     *
     * @return array The merged array of primary and secondary roles.
     */
    public function getRoles(): array {
        return array_merge($this->primaryRoles, $this->secondaryRoles);
    }

    /**
     * Retrieves a list of role options, excluding specified roles, and formats the role names.
     *
     * @param array $exclude A list of roles to exclude from the options.
     * @return array An associative array where the keys are role identifiers and the values are
     *               formatted role names.
     * @throws DependencyException
     * @throws NotFoundException
     */
    public static function getSelectRoleOptions(array $exclude = []): array
    {
        $roles = self::factory()->getRoles();
        $options = [];
        foreach ($roles as $role) {
            if (!in_array($role, $exclude)) {
                $options[$role] = $role;
            }
        }

        // remove _ replace with space the first letter of each word capitalize
        foreach ($options as $value) {
            $options[$value] = ucwords(str_replace('_', ' ', $value));
        }

        return $options;
    }

}