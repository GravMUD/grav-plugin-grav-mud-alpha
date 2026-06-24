<?php

namespace Grav\Plugin;

use Grav\Common\Page\Interfaces\PageContentInterface;
use Grav\Common\Plugin;
use Grav\Plugin\GravMudAlpha\MudAlphaCompiler;
use Grav\Plugin\GravMudAlpha\MudHeadlessApiBridgeController;
use Grav\Plugin\GravMudAlpha\MudHeadlessRouter;
use RocketTheme\Toolbox\Event\Event;

class GravMudAlphaPlugin extends Plugin
{
    private ?MudAlphaCompiler $compiler = null;

    public static function getSubscribedEvents(): array
    {
        $events = [
            'onPluginsInitialized' => [
                ['preloadHeadlessBridge', 100001],
                ['registerMudPageExtension', 10000],
                ['interceptHeadlessApi', 9999],
            ],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onPageNotFound' => ['onPagesInitialized', 0],
            'onPageInitialized' => ['onPageInitialized', 0],
            'onPageContentRaw' => ['onPageContentRaw', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onOutputGenerated' => ['onOutputGenerated', 0],
        ];

        if (self::supportsGravApiBridge()) {
            $events['onApiRegisterRoutes'] = ['onApiRegisterRoutes', 0];
            $events['onApiCollectPublicRoutes'] = ['onApiCollectPublicRoutes', 0];
        }

        return $events;
    }

    /** Preload Grav 2 API bridge classes (optional path when /api/v1 works). */
    public function preloadHeadlessBridge(): void
    {
        if (!$this->isEnabled() || !self::supportsGravApiBridge()) {
            return;
        }

        require_once __DIR__ . '/classes/MudHeadlessApiBridgeController.php';
        require_once __DIR__ . '/classes/MudHeadlessApi.php';
    }

    /**
     * Bypass Grav API middleware for /api/mud — same pattern as GetGRAV goggrav router.
     * Runs after .mud extension registration, before login@1000 (early exit like goggrav).
     */
    public function interceptHeadlessApi(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $cfg = (array) $this->grav['config']->get('plugins.grav-mud-alpha', []);
        $apiPrefix = trim((string) ($cfg['headless_api_route'] ?? 'api/mud'), '/');
        $path = trim((string) $this->grav['uri']->path(), '/');

        if ($path !== $apiPrefix && !str_starts_with($path, $apiPrefix . '/')) {
            return;
        }

        require_once __DIR__ . '/classes/MudHeadlessRouter.php';
        $this->ensurePagesReady();
        (new MudHeadlessRouter($this->grav, $cfg))->handle();
    }

    public function onPagesInitialized(): void
    {
        if (!$this->isEnabled() || $this->isAdmin()) {
            return;
        }

        require_once __DIR__ . '/classes/MudHeadlessRouter.php';
        $cfg = (array) $this->grav['config']->get('plugins.grav-mud-alpha', []);
        (new MudHeadlessRouter($this->grav, $cfg))->handle();
    }

    public function onApiRegisterRoutes(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        require_once __DIR__ . '/classes/MudHeadlessApiBridgeController.php';

        $routes = $event['routes'];
        $controller = [MudHeadlessApiBridgeController::class, 'handle'];

        $routes->addRoute(['GET', 'OPTIONS'], '/mud', $controller);
        $routes->addRoute(['GET', 'OPTIONS'], '/mud/{subpath:.+}', $controller);
    }

    public function onApiCollectPublicRoutes(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $apiBase = (string) ($event['api_base'] ?? '/api/v1');
        $prefixes = (array) ($event['prefixes'] ?? []);
        $prefixes[] = rtrim($apiBase, '/') . '/mud';
        $legacy = '/' . trim((string) $this->grav['config']->get('plugins.grav-mud-alpha.headless_api_route', 'api/mud'), '/');
        $prefixes[] = $legacy;
        $event['prefixes'] = array_values(array_unique($prefixes));
    }

    /**
     * Grav only discovers .md by default; register .mud before pages->init().
     */
    public function registerMudPageExtension(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $language = $this->grav['language'];
        $extensions = $language->getFallbackPageExtensions();
        if (in_array('.mud', $extensions, true)) {
            return;
        }

        try {
            $refl = new \ReflectionClass($language);
            if (!$refl->hasProperty('fallback_extensions')) {
                $this->grav['log']->error('[grav-mud-alpha] Language::fallback_extensions missing — .mud pages may not be discovered.');
                return;
            }

            $prop = $refl->getProperty('fallback_extensions');
            $prop->setAccessible(true);
            $cache = $prop->getValue($language);

            $cache['.md-default-0'] = array_values(array_unique(array_merge(['.mud'], $extensions)));

            $prop->setValue($language, $cache);
        } catch (\ReflectionException $e) {
            $this->grav['log']->error('[grav-mud-alpha] Could not register .mud extension: ' . $e->getMessage());
            return;
        }

        if (!in_array('.mud', $language->getFallbackPageExtensions(), true)) {
            $this->grav['log']->warning('[grav-mud-alpha] .mud extension registration did not take effect.');
        }
    }

    public function onPageContentRaw(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $page = $event['page'] ?? null;
        if (!$page || !$this->isMudPage($page)) {
            return;
        }

        $raw = (string) $page->rawMarkdown();
        $html = $this->getCompiler()->compile($raw);

        if ($page instanceof PageContentInterface) {
            $page->setRawContent($html);
            return;
        }

        if (method_exists($page, 'setRawContent')) {
            $page->setRawContent($html);
            return;
        }

        $prop = new \ReflectionProperty($page, 'content');
        $prop->setAccessible(true);
        $prop->setValue($page, $html);
    }

    public function onPageInitialized(Event $event): void
    {
        if (!$this->isEnabled() || !$this->isEmbedRequest()) {
            return;
        }

        $page = $event['page'] ?? null;
        if ($page && method_exists($page, 'template')) {
            $page->template('embed');
        }
    }

    public function onTwigSiteVariables(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $twig = $this->grav['twig'];
        $twig->twig_vars['grav_mud_alpha'] = [
            'name' => 'Grav MUD Alpha',
            'version' => '0.7.3',
            'notation' => 'NEXT Object Notation',
            'format' => 'MarkUpDown Design Spec (.mud)',
        ];

        $baseUrl = rtrim((string) $this->grav['base_url'], '/');
        $userPath = defined('GRAV_USER_PATH') ? trim(GRAV_USER_PATH, '/') : 'user';
        $fencesCss = GRAV_ROOT . '/user/plugins/grav-mud-alpha/assets/css/grav-mud-fences.css';
        if (is_file($fencesCss)) {
            $twig->twig_vars['grav_mud_fences_css_url'] = $baseUrl . '/' . $userPath . '/plugins/grav-mud-alpha/assets/css/grav-mud-fences.css';
        }

        if (!$this->isEmbedRequest()) {
            return;
        }

        $activeTheme = (string) $this->grav['config']->get('system.pages.theme', 'grav-mud-site');
        $styles = [$twig->twig_vars['grav_mud_fences_css_url'] ?? ($baseUrl . '/' . $userPath . '/plugins/grav-mud-alpha/assets/css/grav-mud-fences.css')];

        if ($activeTheme === 'grav-mud-site') {
            $styles[] = $baseUrl . '/' . $userPath . '/themes/grav-mud-site/css/grav-mud.css';
        } elseif ($activeTheme !== '') {
            $themeCss = GRAV_ROOT . '/user/themes/' . $activeTheme . '/css/' . $activeTheme . '.css';
            if (is_file($themeCss)) {
                $styles[] = $baseUrl . '/' . $userPath . '/themes/' . $activeTheme . '/css/' . $activeTheme . '.css';
            }
            $themeMud = GRAV_ROOT . '/user/themes/' . $activeTheme . '/css/cursy-mud.css';
            if (is_file($themeMud)) {
                $styles[] = $baseUrl . '/' . $userPath . '/themes/' . $activeTheme . '/css/cursy-mud.css';
            }
        }

        $bodyClass = 'grav-mud-embed';
        if (str_starts_with($activeTheme, 'grav-mud-')) {
            $suffix = substr($activeTheme, strlen('grav-mud-'));
            if ($suffix !== '' && $suffix !== 'site') {
                $bodyClass .= ' mud-' . $suffix . '-body';
            }
        }

        $twig->twig_vars['grav_mud_embed'] = true;
        $twig->twig_vars['grav_mud_embed_styles'] = $styles;
        $twig->twig_vars['grav_mud_embed_body_class'] = $bodyClass;

        $extension = $this->grav['uri']->extension() ?: 'html';
        $twig->template = 'embed.' . $extension . '.twig';
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onOutputGenerated(Event $event): void
    {
        if (!$this->isEnabled() || !$this->isEmbedRequest()) {
            return;
        }

        header('Content-Security-Policy: frame-ancestors *');
        header_remove('X-Frame-Options');
    }

    private function isEmbedRequest(): bool
    {
        $uri = $this->grav['uri'];
        $value = $uri->param('gravmud-embed')
            ?: $uri->param('gravity-embed')
            ?: $uri->query('gravmud-embed')
            ?: $uri->query('gravity-embed')
            ?: ($_GET['gravmud-embed'] ?? null)
            ?: ($_GET['gravity-embed'] ?? null);

        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function getCompiler(): MudAlphaCompiler
    {
        if ($this->compiler === null) {
            require_once __DIR__ . '/classes/MudAlphaCompiler.php';
            $this->compiler = new MudAlphaCompiler();
            $this->compiler->setGrav($this->grav);
            $theme = (string) $this->grav['config']->get('system.pages.theme', 'grav-mud-site');
            $themeUrl = $this->grav['base_url'] . '/user/themes/' . $theme . '/images';
            $this->compiler->setAssetBase($themeUrl);
            if ($this->grav['config']->get('plugins.grav-mud-commentz.enabled')) {
                $commentRoute = trim((string) $this->grav['config']->get('plugins.grav-mud-commentz.api_route', 'api/mud-commentz'), '/');
                $this->compiler->setCommentApiBase('/' . $commentRoute);
            }
            $msgCfg = (array) $this->grav['config']->get('plugins.messenger', []);
            if ($msgCfg === []) {
                $msgCfg = (array) $this->grav['config']->get('plugins.grav-mud-messenger', []);
            }
            if (!empty($msgCfg['enabled'])) {
                $messengerRoute = trim((string) ($msgCfg['api_route'] ?? 'api/mud-messenger'), '/');
                $this->compiler->setMessengerApiBase('/' . $messengerRoute);
            }
            if ($this->grav['config']->get('plugins.grav-mud-marketplace.enabled')) {
                $shopRoute = trim((string) $this->grav['config']->get('plugins.grav-mud-marketplace.route_prefix', 'shop'), '/');
                $this->compiler->setMarketplaceRoute($shopRoute);
            }
            $flipBase = trim((string) $this->grav['config']->get('theme.flipzine.base_url', ''));
            if ($flipBase !== '') {
                $this->compiler->setFlipzineBase($flipBase);
            }
            $flipSlug = trim((string) $this->grav['config']->get('theme.flipzine.default_slug', ''));
            if ($flipSlug !== '') {
                $this->compiler->setFlipzineDefaultSlug($flipSlug);
            }
            $swagCfg = class_exists(\Grav\Plugin\SwagStorePlugin::class)
                ? \Grav\Plugin\SwagStorePlugin::pluginConfig($this->grav)
                : (array) ($this->grav['config']->get('plugins.swag-store') ?: $this->grav['config']->get('plugins.grav-mud-swag-store', []));
            if (!empty($swagCfg['enabled'])) {
                $swagRoute = trim((string) ($swagCfg['route_prefix'] ?? 'shop'), '/');
                $this->compiler->setSwagRoute($swagRoute);
            }
        }
        return $this->compiler;
    }

    private function isMudPage($page): bool
    {
        $header = $page->header();
        $fmt = strtolower((string) ($header->format ?? ''));
        if (in_array($fmt, ['mud', 'mud-spec'], true)) {
            return true;
        }
        if (!empty($header->content_format) && strtolower((string) $header->content_format) === 'mud') {
            return true;
        }
        $template = $page->template() ?? '';
        if (str_ends_with(strtolower($template), '.mud')) {
            return true;
        }
        $name = $page->name() ?? '';
        return str_ends_with(strtolower($name), '.mud');
    }

    private function isEnabled(): bool
    {
        return (bool) $this->grav['config']->get('plugins.grav-mud-alpha.enabled', true);
    }

    private static function supportsGravApiBridge(): bool
    {
        return class_exists(\Grav\Plugin\Api\ApiRouteCollector::class);
    }

    private function ensurePagesReady(): void
    {
        $pages = $this->grav['pages'];
        if (!method_exists($pages, 'init')) {
            return;
        }

        $pages->init();

        if (self::rootChildCount($pages) > 0) {
            return;
        }

        try {
            $refl = new \ReflectionClass($pages);
            if (!$refl->hasProperty('initialized')) {
                return;
            }
            $prop = $refl->getProperty('initialized');
            $prop->setAccessible(true);
            $prop->setValue($pages, false);
            $pages->init();
        } catch (\ReflectionException) {
        }
    }

    private static function rootChildCount(object $pages): int
    {
        try {
            $count = 0;
            foreach ($pages->root()->children() as $_) {
                $count++;
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }
}
