<?php

declare(strict_types=1);

namespace Grav\Plugin\GravMudAlpha;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
/**
 * Public headless read of Grav .mud pages for MUD Shell (no API key).
 */
final class MudPublicPages
{
    /** @var list<string> */
    private const EXCLUDE_SLUGS = ['login', 'user_register', 'error'];

    /** @return list<array<string, mixed>> */
    public static function listing(Grav $grav): array
    {
        $items = [];
        $seenRoutes = [];

        foreach (self::topLevelPages($grav) as $page) {
            if (!$page instanceof PageInterface || !$page->exists()) {
                continue;
            }
            if (!self::isPublicHeadlessPage($grav, $page)) {
                continue;
            }

            $route = (string) $page->route();
            if (isset($seenRoutes[$route])) {
                continue;
            }
            $seenRoutes[$route] = true;

            $items[] = self::summary($grav, $page);
        }

        usort($items, static fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $items;
    }

    /** @return array<string, mixed>|null */
    public static function get(Grav $grav, string $slug): ?array
    {
        $page = $grav['pages']->find(self::slugToRoute($slug));
        if (!$page instanceof PageInterface || !$page->exists() || !self::isPublicHeadlessPage($grav, $page)) {
            return null;
        }

        return self::detail($grav, $page);
    }

    /** @return iterable<PageInterface> */
    private static function topLevelPages(Grav $grav): iterable
    {
        $root = $grav['pages']->root();
        $children = [];
        foreach ($root->children() as $page) {
            if ($page instanceof PageInterface) {
                $children[] = $page;
            }
        }
        if ($children !== []) {
            return $children;
        }

        if (!method_exists($grav['pages'], 'all')) {
            return [];
        }

        $fallback = [];
        foreach ($grav['pages']->all() as $page) {
            if (!$page instanceof PageInterface) {
                continue;
            }
            $route = trim((string) $page->route(), '/');
            if ($route === '' || str_contains($route, '/')) {
                continue;
            }
            $fallback[] = $page;
        }

        return $fallback;
    }

    private static function isPublicHeadlessPage(Grav $grav, PageInterface $page): bool
    {
        if (!$page->published() || !$page->visible() || !$page->routable()) {
            return false;
        }

        if (in_array($page->slug(), self::EXCLUDE_SLUGS, true)) {
            return false;
        }

        if (!self::isMudPage($page)) {
            return false;
        }

        return self::isHeadlessExposed($grav, $page, MudHeadlessConfig::resolve($grav));
    }

    private static function isMudPage(PageInterface $page): bool
    {
        $header = $page->header();
        $format = is_object($header) ? strtolower((string) ($header->format ?? '')) : '';
        if (in_array($format, ['mud', 'mud-spec'], true)) {
            return true;
        }

        $filePath = strtolower((string) $page->filePath());
        $name = strtolower((string) ($page->name() ?? ''));
        $template = strtolower((string) ($page->template() ?? ''));

        return str_ends_with($filePath, '.mud')
            || str_ends_with($name, '.mud')
            || str_ends_with($template, '.mud');
    }

    /** @param array<string, mixed> $cfg */
    private static function isHeadlessExposed(Grav $grav, PageInterface $page, array $cfg): bool
    {
        $header = $page->header();
        if (is_object($header) && isset($header->headless)) {
            $forced = self::toBool($header->headless);
            if ($forced === false) {
                return false;
            }
            if ($forced === true) {
                return true;
            }
        }

        $mode = strtolower((string) ($cfg['headless_mode'] ?? 'all'));
        if ($mode === 'opt-in') {
            return false;
        }

        $design = self::mudDesignName($page);
        $exclude = self::configStringList($cfg, 'headless_exclude_designs');
        if ($design !== null && in_array($design, $exclude, true)) {
            return false;
        }

        $include = self::configIncludeDesigns($cfg);
        if ($mode === 'design') {
            if ($include === []) {
                return true;
            }

            return $design !== null && in_array($design, $include, true);
        }

        if ($include !== []) {
            return $design !== null && in_array($design, $include, true);
        }

        return true;
    }

    /** @param array<string, mixed> $cfg */
    /** @return list<string> */
    private static function configIncludeDesigns(array $cfg): array
    {
        $include = self::configStringList($cfg, 'headless_include_designs');
        if ($include !== []) {
            return $include;
        }

        return self::configStringList($cfg, 'headless_designs');
    }

    /** @param array<string, mixed> $cfg */
    /** @return list<string> */
    private static function configStringList(array $cfg, string $key): array
    {
        $values = $cfg[$key] ?? null;
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $values), static fn (string $v): bool => $v !== ''));
    }

    private static function toBool(mixed $value): ?bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }

    private static function mudDesignName(PageInterface $page): ?string
    {
        $name = self::parseDesignNameFromRaw((string) $page->rawMarkdown());
        if ($name !== null) {
            return $name;
        }

        $path = (string) $page->filePath();
        if ($path !== '' && is_readable($path)) {
            return self::parseDesignNameFromRaw((string) file_get_contents($path));
        }

        return null;
    }

    private static function parseDesignNameFromRaw(string $raw): ?string
    {
        if (!preg_match('/@@@[\s\S]*?\bname:\s*([^\r\n#]+)/', $raw, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    /** @return array{mode: string, include_designs: list<string>, exclude_designs: list<string>, config_source: string} */
    public static function headlessFilterConfig(Grav $grav): array
    {
        $cfg = MudHeadlessConfig::resolve($grav);
        $runtime = (array) $grav['config']->get('plugins.grav-mud-alpha', []);

        return [
            'mode' => strtolower((string) ($cfg['headless_mode'] ?? 'all')),
            'include_designs' => self::configIncludeDesigns($cfg),
            'exclude_designs' => self::configStringList($cfg, 'headless_exclude_designs'),
            'config_source' => $cfg === $runtime ? 'runtime' : 'runtime+user-yaml',
        ];
    }

    private static function slugToRoute(string $slug): string
    {
        $slug = trim(strtolower(rawurldecode($slug)), '/');

        return ($slug === '' || $slug === 'home') ? '/' : '/' . $slug;
    }

    private static function routeToSlug(string $route): string
    {
        $route = trim($route, '/');

        return $route === '' ? 'home' : $route;
    }

    /** @return array<string, mixed> */
    private static function summary(Grav $grav, PageInterface $page): array
    {
        $header = $page->header();
        $meta = is_object($header) && is_array($header->metadata ?? null) ? $header->metadata : [];

        return [
            'slug' => self::routeToSlug($page->route()),
            'route' => $page->route(),
            'title' => (string) $page->title(),
            'menu' => (string) ($header->menu ?? $page->title()),
            'description' => (string) ($meta['description'] ?? ''),
            'order' => (int) $page->order(),
            'canonical_url' => self::absoluteUrl($grav, (string) $page->url()),
            'design' => self::mudDesignName($page) ?? '',
        ];
    }

    /** @return array<string, mixed> */
    private static function detail(Grav $grav, PageInterface $page): array
    {
        $grav['page'] = $page;

        try {
            require_once __DIR__ . '/MudHeadlessCompiler.php';
            $html = MudHeadlessCompiler::compile($grav, (string) $page->rawMarkdown());
        } catch (\Throwable) {
            $html = '<p>Could not render this page.</p>';
        }

        $header = $page->header();
        $meta = is_object($header) && is_array($header->metadata ?? null) ? $header->metadata : [];

        return [
            'slug' => self::routeToSlug($page->route()),
            'route' => $page->route(),
            'title' => (string) $page->title(),
            'menu' => (string) ($header->menu ?? $page->title()),
            'description' => (string) ($meta['description'] ?? ''),
            'body_html' => self::rewriteUrls($grav, $html),
            'modified' => (int) $page->modified(),
            'canonical_url' => self::absoluteUrl($grav, (string) $page->url()),
            'design' => self::mudDesignName($page) ?? '',
            'theme_css' => self::themeCssUrl($grav),
            'theme_styles' => self::themeStyles($grav),
        ];
    }

    /** @return list<string> */
    private static function themeStyles(Grav $grav): array
    {
        $userPath = defined('GRAV_USER_PATH') ? trim(GRAV_USER_PATH, '/') : 'user';
        $styles = [self::absoluteUrl($grav, '/' . $userPath . '/plugins/grav-mud-alpha/assets/css/grav-mud-fences.css')];
        $theme = (string) $grav['config']->get('system.pages.theme', 'grav-mud-site');
        if ($theme === 'grav-mud-site') {
            $styles[] = self::absoluteUrl($grav, '/' . $userPath . '/themes/grav-mud-site/css/grav-mud.css');
        } elseif ($theme !== '') {
            $styles[] = self::absoluteUrl($grav, '/' . $userPath . '/themes/' . $theme . '/css/' . $theme . '.css');
            $mudBridge = self::absoluteUrl($grav, '/' . $userPath . '/themes/' . $theme . '/css/cursy-mud.css');
            if (is_file(GRAV_ROOT . '/user/themes/' . $theme . '/css/cursy-mud.css')) {
                $styles[] = $mudBridge;
            }
        }

        return $styles;
    }

    private static function themeCssUrl(Grav $grav): string
    {
        $styles = self::themeStyles($grav);

        return $styles[count($styles) - 1] ?? '';
    }

    private static function absoluteUrl(Grav $grav, string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) $grav['base_url_absolute'], '/') . '/' . ltrim($url, '/');
    }

    private static function rewriteUrls(Grav $grav, string $html): string
    {
        $base = rtrim((string) $grav['base_url_absolute'], '/');

        return preg_replace(
            '#\s(href|src|poster)=(["\'])/(?!/)#',
            ' $1=$2' . $base . '/',
            $html
        ) ?? $html;
    }
}
