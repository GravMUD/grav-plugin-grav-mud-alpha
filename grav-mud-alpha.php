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
            'onPageContentRaw' => ['onPageContentRaw', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
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

    public function onTwigSiteVariables(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->grav['twig']->twig_vars['grav_mud_alpha'] = [
            'name' => 'Grav MUD Alpha',
            'version' => '0.5.0',
            'notation' => 'NEXT Object Notation',
            'format' => 'MarkUpDown Design Spec (.mud)',
        ];
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
