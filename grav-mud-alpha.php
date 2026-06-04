<?php

namespace Grav\Plugin;

use Grav\Common\Page\Interfaces\PageContentInterface;
use Grav\Common\Plugin;
use Grav\Plugin\GravMudAlpha\MudAlphaCompiler;
use RocketTheme\Toolbox\Event\Event;

class GravMudAlphaPlugin extends Plugin
{
    private ?MudAlphaCompiler $compiler = null;

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['registerMudPageExtension', 10000],
            'onPageInitialized' => ['onPageInitialized', 0],
            'onPageContentRaw' => ['onPageContentRaw', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onOutputGenerated' => ['onOutputGenerated', 0],
        ];
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
            'version' => '0.6.0',
            'notation' => 'NEXT Object Notation',
            'format' => 'MarkUpDown Design Spec (.mud)',
        ];

        if (!$this->isEmbedRequest()) {
            return;
        }

        $activeTheme = (string) $this->grav['config']->get('system.pages.theme', 'grav-mud-site');
        $baseUrl = rtrim((string) $this->grav['base_url'], '/');
        $styles = [$baseUrl . '/user/themes/grav-mud-site/css/grav-mud.css'];

        if ($activeTheme !== 'grav-mud-site') {
            $childCss = $baseUrl . '/user/themes/' . $activeTheme . '/css/' . $activeTheme . '.css';
            $styles[] = $childCss;
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
            $theme = (string) $this->grav['config']->get('system.pages.theme', 'grav-mud-site');
            $themeUrl = $this->grav['base_url'] . '/user/themes/' . $theme . '/images';
            $this->compiler->setAssetBase($themeUrl);
            if ($this->grav['config']->get('plugins.grav-mud-forumz.enabled')) {
                $forumRoute = trim((string) $this->grav['config']->get('plugins.grav-mud-forumz.api_route', 'api/mud-forumz'), '/');
                $this->compiler->setForumApiBase('/' . $forumRoute);
            }
            if ($this->grav['config']->get('plugins.grav-mud-commentz.enabled')) {
                $commentRoute = trim((string) $this->grav['config']->get('plugins.grav-mud-commentz.api_route', 'api/mud-commentz'), '/');
                $this->compiler->setCommentApiBase('/' . $commentRoute);
            }
            if ($this->grav['config']->get('plugins.grav-mud-messenger.enabled')) {
                $messengerRoute = trim((string) $this->grav['config']->get('plugins.grav-mud-messenger.api_route', 'api/mud-messenger'), '/');
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
            if ($this->grav['config']->get('plugins.grav-mud-swag-store.enabled')) {
                $swagRoute = trim((string) $this->grav['config']->get('plugins.grav-mud-swag-store.route_prefix', 'shop'), '/');
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
}
