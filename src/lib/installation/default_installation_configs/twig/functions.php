<?php

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\auto_path\src\path\AutoPathAlias;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\auth\normal_auth\AuthUser;
use Simp\Core\modules\structures\taxonomy\Term;
use Simp\Translate\lang\LanguageManager;
use Simp\Core\modules\tokens\TokenManager;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\config\config\ConfigReadOnly;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\files\helpers\FileFunction;
use Simp\Core\modules\search\SearchManager;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\entity\User;
use Simp\Translate\translate\Translate;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\TwigFunction;
use Simp\Core\modules\services\Service;

function getContentType(?string $content_name): ?array
{
    if ($content_name === null || $content_name === '' || $content_name === '0') {
        return null;
    }

    return ContentDefinitionManager::contentDefinitionManager()
        ->getContentType($content_name);
}

function getContentTypeField(?string $content_name, ?string $field_name): ?array
{
    if ($content_name === null || $content_name === '' || $content_name === '0' || ($field_name === null || $field_name === '' || $field_name === '0')) {
        return null;
    }

    $content = getContentType($content_name);
    $fields = $content['fields'] ?? [];
    foreach ($fields as $field) {
        if (isset($field['inner_field'])) {
            $result = recursive_function($field['inner_field'], $field_name);
            if ($result) {
                return $result;
            }
        } elseif ($field['name'] == $field_name) {
            return $field;
        }
    }

    return null;
}

function recursive_function(array $fields, string $field_name)
{
    foreach ($fields as $field) {
        if (isset($field['inner_field'])) {
            $result = recursive_function($field['inner_field'], $field_name);
            if ($result) {
                return $result;
            }
        } elseif ($field['name'] == $field_name) {
            return $field;
        }
    }

    return null;
}

function routeByName(?string $route_name)
{
    if ($route_name === null || $route_name === '' || $route_name === '0') {
        return null;
    }

    try {
        return Caching::init()->get($route_name);
    }catch (Throwable){
        return null;
    }
}

function breakLineToHtml(string $text,int $at = 100): string
{
    $text_line = null;
    $counter = 0;
    for($i = 0; $i < strlen($text); $i++) {
        if ($counter === $at) {
            $text_line .= "\n". $text[$i];
            $counter = 0;
        }
        else {
            $text_line .= $text[$i];
        }

        $counter++;
    }

    return nl2br((string) $text_line);
}

/**
 * @throws PhpfastcacheCoreException
 * @throws PhpfastcacheLogicException
 * @throws PhpfastcacheDriverException
 * @throws PhpfastcacheInvalidArgumentException
 */
function url(string $id, array $options, array $params = []): ?string
{
    if (!empty($options['nid']) && empty($options['is_alias'])) {
        $alias = AutoPathAlias::createRouteId($options['nid']);
        $options['is_alias'] = true;
        $found = url($alias, $options, $params);
        if ($found !== null && $found !== '' && $found !== '0') {
            return $found;
        }
    }

    if ($id !== '' && $id !== '0') {
        $route = Caching::init()->get($id);
        if (empty($route) && ModuleHandler::factory()->isModuleEnabled('auto_path')) {
            $routes = AutoPathAlias::injectAliases();
            $route = $routes[$id] ?? null;
        }

        if ($route instanceof Route) {
            $pattern = $route->getRoutePath();
            $generatePath = (fn(string $pattern, array $values): string => getStr($pattern, $values));
            $with_value_pattern = $generatePath($pattern, $options);


            return $params === [] ? $with_value_pattern : $with_value_pattern . '?'. http_build_query($params);
        }
    }

    return null;
}

function getStr(string $pattern, array $values): string
{
    $segments = explode('/', $pattern);

    foreach ($segments as &$segment) {
        if (str_starts_with($segment, '[') && str_ends_with($segment, ']')) {
            // Trim the square brackets
            $placeholder = trim($segment, '[]');

            // Handle possible type e.g., id:int
            $parts = explode(':', $placeholder);
            $key = $parts[0];

            if (isset($values[$key])) {
                $segment = $values[$key];
            }
        }
    }

    return implode('/', $segments);
}

function buildReferenceLink(int|string|array $value, \Simp\Fields\FieldBase $field_definition): array
{
    $html = [];

    if (!is_array($value)) {
        $value = [$value];
    }

    $field_definition = $field_definition->getField();

    foreach ($value as $v) {

        if (!empty($field_definition['type']) && $field_definition['type'] === 'reference') {

            if (!empty($field_definition['reference']['type'])) {

                if ($field_definition['reference']['type'] === 'node') {
                    if (is_numeric($v)) {
                        $link = url('system.structure.content.node',['nid'=>$v]);
                        $node = Node::load($v);
                        if ($node instanceof Node) {
                            $html[] = [
                                'url' => $link,
                                'name' => $node->getTitle(),
                            ];
                        }
                    }
                    else {
                        $html[] =[
                            'url' => '#',
                            'name' => "reference not found"
                        ];
                    }

                }
                elseif ($field_definition['reference']['type'] === 'users') {
                    $link = url('system.account.view:',['uid'=>$v]);
                    $user = User::load($v);
                    if ($user instanceof User) {
                        $html[] = [
                            'url' => $link,
                            'name' => $user->getName()
                        ];
                    }
                }
                elseif ($field_definition['reference']['type'] === 'file') {
                    $file = File::load($v);
                    if ($file instanceof File) {
                        $html[] = [
                            'url' => FileFunction::reserve_uri($file->getUri()),
                            'name' => $file->getName()
                        ];
                    }
                }

                elseif ($field_definition['reference']['type'] === 'term') {
                    if (!empty($v)) {
                        $term = Term::load($v);
                        $uri = url('system.vocabulary.term.view',['name'=>$term['name']]);
                        $html[] = [
                            'url' => $uri,
                            'name' => $term['label']
                        ];
                    }
                    else {
                        $html[] = [
                            'url' => '#',
                            'name' => ''
                        ];
                    }
                }
            }
        }
        elseif (!empty($field_definition['type']) && $field_definition['type'] === 'drag_and_drop') {
            $file = File::load($v);
            if ($file instanceof File) {
                $html[] = [
                    'url' => FileFunction::reserve_uri($file->getUri()),
                    'name' => $file->getName()
                ];
            }
        }

    }

    return $html === [] ? [
        [
            'url' => '#',
            'name' => $value
        ]
    ] : $html;
}

function author(int $uid): ?User {
    return User::load($uid);
}

/**
 * @throws PhpfastcacheCoreException
 * @throws PhpfastcacheLogicException
 * @throws PhpfastcacheDriverException
 * @throws PhpfastcacheInvalidArgumentException
 */
function t(string $text, ?string $from = null, ?string $to = null): string {

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

function translation(?string $code): ?array
{
    if ($code === null || $code === '' || $code === '0') {
        return [];
    }

    return LanguageManager::manager()->getByCode($code);
}

/**
 * @throws RuntimeError
 * @throws SyntaxError
 * @throws LoaderError
 */
function search_api(string $search_key): ?string
{
    return SearchManager::buildForm($search_key);
}

function getFieldTypeInfo(string $type = ''): array
{
    return FieldManager::fieldManager()->getFieldInfo($type);
}

function get_field_type_info(string $type = '', $index = 0, array $field = []): string
{

    $options = $field;
    $options['index'] = $index;
    if ($type === 'textarea') {
        $type = in_array('editor', $field['class'] ?? []) ? 'ck_editor' : 'simple_textarea';
    }

    if ($type === 'conditional') {
        $type = 'fieldset';
    }

    $handler = FieldManager::fieldManager()->getFieldBuilderHandler($type);
    if ($handler instanceof FieldBuilderInterface) {
        return $handler->build(Service::get('request'),$type, $options);
    }

    return '';
}

function file_size_format(int|float $size): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;

    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }

    return round($size, 2) . ' ' . $units[$i];
}

function fieldDisplaySettingsHandler(string $field_type, \Simp\Fields\FieldBase $field, array $context): string
{
    return $field->display($field_type, $field, $context);
}

function get_functions(): array
{
    return [
        new TwigFunction('get_content_type', fn($content_name): ?array => getContentType($content_name)),
        new TwigFunction('get_content_type_field', fn($content_name, $field_name): ?array => getContentTypeField($content_name, $field_name)),
        new TwigFunction('route_by_name', fn($route_name) => routeByName($route_name)),
        new TwigFunction('file_uri', fn($fid): string => FileFunction::resolve_fid($fid)),
        new TwigFunction('file', fn($fid): ?\Simp\Core\modules\files\entity\File => FileFunction::file($fid)),
        new TwigFunction('br', fn($text, $at= 100): string => breakLineToHtml($text,$at)),
        new TwigFunction('url', fn($url, $options = [], $params = []): ?string => url($url, $options, $params)),
        new TwigFunction('search_form', fn($search_key, $wrapper = false): ?string => search_api($search_key)),
        new TwigFunction('author', fn($uid): ?\Simp\Core\modules\user\entity\User => author($uid)),
        new TwigFunction('t',fn(string $text, ?string $from = null, ?string $to = null): string => t($text, $from, $to)),
        new TwigFunction('translation',fn(?string $code): ?array => translation($code)),
        new TwigFunction('getFieldTypeInfo',fn(string $type): array => getFieldTypeInfo($type)),
        new TwigFunction('get_field_type_info',fn(string $type, $index = 0, array $field = []): string => get_field_type_info($type, $index, $field)),
        new TwigFunction('tokens_floating_window',fn(): string => TokenManager::token()->getFloatingWindow()),
        new TwigFunction('reference_link',fn(int|string|array $value, \Simp\Fields\FieldBase $field_definition): array => buildReferenceLink($value, $field_definition)),
        new TwigFunction('size_format',fn(int|float $size): string => file_size_format($size)),
        new TwigFunction('is_module_enabled',fn(string $module_name): bool => ModuleHandler::factory()->isModuleEnabled($module_name)),

        new TwigFunction('attached_library',function(string $section): string{
            $sections = $GLOBALS['theme'][$section] ?? [];
            return implode('',$sections);

        }),
        new TwigFunction('attach_library',function(string $section, string $file): void {
            $GLOBALS['theme'][$section][] = $file;
            $GLOBALS['theme'][$section] = array_unique($GLOBALS['theme'][$section]);
        }),

        new TwigFunction('auto_path_key',fn(int $number): string => AutoPathAlias::createRouteId($number)),
        new TwigFunction('base64',fn(string $file_path): string => FileFunction::base64_file($file_path)),
        new TwigFunction('field_display_settings',fn(string $field_type, \Simp\Fields\FieldBase $field, array $context): string => fieldDisplaySettingsHandler($field_type, $field, $context))
    ];
}