<?php

namespace Grav\Plugin\GravMudAlpha;

/**
 * Grav MUD Alpha — Design Spec renderer (v0.3).
 * Maps ::: spec fences + @@@ design blocks to layout primitives (promo, docs, services).
 */
class MudDesignSpec
{
    private string $assetBase = '/user/themes/grav-mud-alpha/images/';

    /** @var 'off'|'on'|'reduced-only' */
    private string $pageMotion = 'off';

    public function setAssetBase(string $base): void
    {
        $this->assetBase = rtrim($base, '/') . '/';
    }

    /** @param array<string, mixed> $node */
    public function renderFence(array $node, callable $renderChildren, callable $renderMarkdown): string
    {
        $t = strtolower((string) ($node['fenceType'] ?? ''));
        $body = (string) ($node['body'] ?? '');
        $attrs = $node['attrs'] ?? [];
        $data = $this->parseStructuredBody($body);

        switch ($t) {
            case 'spec':
            case 'design':
                return $this->renderDesignBlock($data, $attrs);
            case 'hero':
            case 'spec-hero':
                return $this->renderHero($data, $attrs);
            case 'quote':
            case 'spec-quote':
                return $this->renderQuote($data, $attrs);
            case 'trophies':
            case 'spec-trophies':
                return $this->renderTrophies($data, $attrs);
            case 'badges':
            case 'spec-badges':
                return $this->renderBadges($data, $attrs, $renderMarkdown);
            case 'wiki':
            case 'spec-wiki':
                return $this->renderWiki($data, $attrs, $renderMarkdown);
            case 'teeman':
            case 'profile':
            case 'spec-profile':
                return $this->renderProfile($data, $attrs, $renderMarkdown);
            case 'guestbook':
            case 'spec-guestbook':
                return $this->renderGuestbook($data, $attrs, $renderMarkdown);
            case 'cards':
            case 'spec-cards':
                return $this->renderCards($data, $attrs);
            case 'timeline':
            case 'spec-timeline':
                return $this->renderTimeline($data, $attrs);
            case 'compare':
            case 'spec-compare':
                return $this->renderCompare($data, $attrs, $renderMarkdown);
            case 'receipts':
            case 'spec-receipts':
                return $this->renderReceipts($data, $attrs, $renderMarkdown);
            case 'manifesto':
            case 'spec-manifesto':
                return $this->renderManifesto($data, $attrs, $renderMarkdown);
            case 'blog-teaser':
            case 'spec-blog-teaser':
                return $this->renderBlogTeaser($data, $attrs);
            case 'blog-index':
            case 'spec-blog-index':
                return $this->renderBlogIndex($data, $attrs);
            case 'blog-post-header':
            case 'spec-blog-post-header':
                return $this->renderBlogPostHeader($data, $attrs);
            case 'blog-body':
            case 'spec-blog-body':
                return $this->renderBlogBody($data, $attrs, $renderMarkdown);
            case 'pricing':
            case 'spec-pricing':
                return $this->renderPricing($data, $attrs);
            case 'code':
            case 'spec-code':
                return $this->renderCode($data, $attrs);
            case 'video':
            case 'spec-video':
                return $this->renderVideo($data, $attrs);
            case 'theme':
            case 'spec-theme':
                return $this->renderTheme($data, $attrs);
            case 'gallery':
            case 'spec-gallery':
                return $this->renderGallery($data, $attrs, $body);
            case 'carousel':
            case 'spec-carousel':
                return $this->renderCarousel($data, $attrs, $body);
            default:
                return '';
        }
    }

    /** @param array<string, mixed> $spec */
    public function renderDesignOpen(array $spec): string
    {
        $name = $this->esc((string) ($spec['name'] ?? 'mamber-dark'));
        $layout = $this->esc((string) ($spec['layout'] ?? 'expose'));
        $css = $this->buildTokenCss($spec['tokens'] ?? []);
        $motionRaw = strtolower(trim((string) ($spec['motion'] ?? 'off')));
        $motionClass = '';
        if ($this->isTruthy($motionRaw) || $motionRaw === 'on') {
            $this->pageMotion = 'on';
            $motionClass = ' mud-motion';
        } elseif ($motionRaw === 'reduced-only') {
            $this->pageMotion = 'reduced-only';
            $motionClass = ' mud-motion mud-motion--reduced-only';
        } else {
            $this->pageMotion = 'off';
        }

        return '<div class="mud-page mud-page--' . $name . $motionClass . '" data-mud-layout="' . $layout . '" data-mud-motion="' . $this->esc($this->pageMotion) . '">'
            . ($css ? '<style>' . $css . '</style>' : '');
    }

    public function renderDesignClose(): string
    {
        $this->pageMotion = 'off';

        return '</div>';
    }

    /** @param array<string, string> $tokens */
    private function buildTokenCss(array $tokens): string
    {
        if (!$tokens) {
            return '';
        }
        $map = [
            'bg' => '--bg',
            'bg-card' => '--bg-card',
            'panel' => '--bg-card',
            'text' => '--text',
            'muted' => '--muted',
            'accent' => '--accent',
            'accent-glow' => '--accent-glow',
            'gold' => '--gold',
            'teal' => '--teal',
            'border' => '--border',
        ];
        $rules = [];
        foreach ($tokens as $key => $val) {
            $var = $map[$key] ?? ('--mud-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($key)));
            $rules[] = $var . ': ' . $val . ';';
        }
        return '.mud-page { ' . implode(' ', $rules) . ' color: var(--text); background: var(--bg); }';
    }

    /** @param array<string, mixed> $data */
    private function renderDesignBlock(array $data, array $attrs): string
    {
        $spec = array_merge($data, $attrs);
        return $this->renderDesignOpen($spec);
    }

    /** @param array<string, mixed> $data */
    private function renderHero(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $variant = strtolower(trim((string) ($attrs['variant'] ?? $data['variant'] ?? 'default')));
        $variant = preg_replace('/[^a-z0-9-]/', '', $variant) ?? 'default';
        if ($variant === '') {
            $variant = 'default';
        }

        if ($variant === 'split') {
            return $this->renderHeroSplit($data, $attrs, $id);
        }

        $inner = $this->buildHeroInner($data);
        $classes = ['hero'];
        if ($variant !== 'default') {
            $classes[] = 'hero--' . $variant;
        }
        $align = strtolower(trim((string) ($data['align'] ?? '')));
        if (in_array($align, ['center', 'right'], true)) {
            $classes[] = 'hero--align-' . $align;
        }

        $bgRaw = trim((string) ($data['background'] ?? $data['bg'] ?? ''));
        $styleVars = $this->heroStyleVars($data, $variant, $bgRaw);
        $scrollHero = $this->sanitizeScrollHero(
            (string) ($attrs['scroll-hero'] ?? $data['scroll-hero'] ?? $data['scroll'] ?? '')
        );
        $scrollLength = $this->heroScrollLength($data, $attrs);
        if ($scrollHero !== '') {
            $classes[] = 'hero--scroll-hero';
        }

        $motion = $scrollHero !== ''
            ? ['extraClass' => '', 'dataAttr' => '']
            : $this->motionFragment($attrs, $data, '');
        if ($motion['extraClass'] !== '') {
            $classes[] = trim($motion['extraClass']);
        }

        $parallax = strtolower(trim((string) ($data['parallax'] ?? '')));
        $parallaxAttr = ($parallax !== '' && $parallax !== 'none')
            ? ' data-hero-parallax="' . $this->esc($parallax) . '"' : '';

        $scrollAttr = $scrollHero !== ''
            ? ' data-scroll-hero="' . $this->esc($scrollHero) . '" data-scroll-length="' . $this->esc((string) $scrollLength) . '"'
            : '';

        $styleAttr = $this->inlineStyle($styleVars);
        $hasBackdrop = $bgRaw !== '' || in_array($variant, ['fullscreen', 'banner'], true);
        $backdrop = $hasBackdrop
            ? '<div class="hero-media" aria-hidden="true"></div><div class="hero-scrim" aria-hidden="true"></div>'
            : '';

        $core = $backdrop . '<div class="hero-inner">' . $inner . '</div>';
        if ($scrollHero !== '') {
            $core = '<div class="hero-scroll-pin">' . $core . '</div>';
        }

        return '<section class="' . implode(' ', $classes) . '"' . $id . $motion['dataAttr'] . $parallaxAttr . $scrollAttr . $styleAttr . '>'
            . $core
            . '</section>';
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $attrs
     */
    private function renderHeroSplit(array $data, array $attrs, string $id): string
    {
        $inner = $this->buildHeroInner($data);
        $mediaSide = strtolower(trim((string) ($data['media-side'] ?? $data['media_side'] ?? 'left')));
        $classes = ['hero', 'hero--split'];
        if ($mediaSide === 'right') {
            $classes[] = 'hero--split-media-right';
        }

        $motion = $this->motionFragment($attrs, $data, '');
        if ($motion['extraClass'] !== '') {
            $classes[] = trim($motion['extraClass']);
        }

        $bgRaw = trim((string) ($data['background'] ?? $data['bg'] ?? ''));
        $styleVars = $this->heroStyleVars($data, 'split', $bgRaw);
        $styleAttr = $this->inlineStyle($styleVars);

        $mediaHtml = $this->renderHeroSplitMedia($data, $bgRaw);
        $copy = '<div class="hero-split-copy hero-inner">' . $inner . '</div>';
        $grid = '<div class="hero-split-grid">'
            . '<div class="hero-split-media">' . $mediaHtml . '</div>'
            . $copy
            . '</div>';

        return '<section class="' . implode(' ', $classes) . '"' . $id . $motion['dataAttr'] . $styleAttr . '>'
            . $grid
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function buildHeroInner(array $data): string
    {
        $mascotRaw = trim((string) ($data['mascot'] ?? ''));
        $mascot = ($mascotRaw !== '' && strtolower($mascotRaw) !== 'none')
            ? $this->mediaUrl($mascotRaw) : '';
        $eyebrow = (string) ($data['eyebrow'] ?? '');
        $title = (string) ($data['title'] ?? '');
        $accent = (string) ($data['accent'] ?? '');
        $lead = (string) ($data['lead'] ?? $data['subtitle'] ?? '');

        $h1 = $title;
        if ($accent !== '') {
            $h1 = $this->inline($title) . '<br /><span class="accent">' . $this->inline($accent) . '</span>';
        } else {
            $h1 = $this->inline($title);
        }

        $ctas = '';
        if (!empty($data['cta']) && is_array($data['cta'])) {
            foreach ($data['cta'] as $item) {
                $ctas .= $this->renderBtn((string) $item);
            }
        }

        $brandInner = ($mascot !== '')
            ? '<div class="hero-mascot-card"><img src="' . $this->esc($mascot) . '" alt="" class="hero-mascot" width="74" height="144" aria-hidden="true" /></div>'
            : '<div class="hero-mark" aria-hidden="true">MUD</div>';

        return '<div class="hero-brand">'
            . $brandInner
            . '<div class="hero-brand-copy">'
            . ($eyebrow ? '<p class="eyebrow">' . $this->esc($eyebrow) . '</p>' : '')
            . ($title ? '<h1>' . $h1 . '</h1>' : '')
            . '</div></div>'
            . ($lead ? '<p class="lead">' . $this->inline($lead) . '</p>' : '')
            . ($ctas ? '<div class="hero-actions">' . $ctas . '</div>' : '');
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function heroStyleVars(array $data, string $variant, string $bgRaw): array
    {
        $styleVars = [];
        if ($bgRaw !== '') {
            $styleVars[] = '--hero-bg: ' . $this->cssUrl($this->mediaUrl($bgRaw));
        }
        $overlay = trim((string) ($data['overlay'] ?? ''));
        if ($overlay !== '') {
            $styleVars[] = '--hero-overlay: ' . $this->esc($overlay);
        }
        $overlayColor = trim((string) ($data['overlay-color'] ?? $data['overlay_color'] ?? ''));
        if ($overlayColor !== '') {
            $styleVars[] = '--hero-overlay-color: ' . $this->esc($overlayColor);
        }
        $height = trim((string) ($data['height'] ?? ''));
        if ($height === '' && $variant === 'fullscreen') {
            $height = '100dvh';
        } elseif ($height === '' && $variant === 'banner') {
            $height = '40vh';
        }
        if ($height !== '') {
            $styleVars[] = '--hero-height: ' . $this->esc($height);
        }

        return $styleVars;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $attrs
     */
    private function heroScrollLength(array $data, array $attrs): float
    {
        $raw = trim((string) ($attrs['scroll-length'] ?? $data['scroll-length'] ?? $data['scroll_length'] ?? '1.4'));
        $len = (float) $raw;

        return max(1.0, min(3.0, $len > 0 ? $len : 1.4));
    }

    private function sanitizeScrollHero(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === '' || in_array($raw, ['off', 'false', '0', 'none'], true)) {
            return '';
        }
        if (in_array($raw, ['apple', 'story', 'product'], true)) {
            return 'cinematic';
        }
        $allowed = ['fade', 'zoom', 'cinematic'];

        return in_array($raw, $allowed, true) ? $raw : '';
    }

    /** @param array<string, mixed> $data */
    private function renderHeroSplitMedia(array $data, string $bgRaw): string
    {
        $media = trim((string) ($data['media'] ?? $data['image'] ?? ''));
        if ($media !== '') {
            $alt = trim((string) ($data['media-alt'] ?? $data['image-alt'] ?? ''));

            return '<img src="' . $this->esc($this->mediaUrl($media)) . '" alt="' . $this->esc($alt) . '" loading="eager" />';
        }

        $videoData = $data;
        if ($this->isTruthy($data['media-autoplay'] ?? $data['autoplay'] ?? false)) {
            $videoData['autoplay'] = true;
            $videoData['muted'] = true;
            $videoData['loop'] = $videoData['loop'] ?? true;
        }

        $embed = $this->buildVideoEmbed($videoData);
        if ($embed !== '') {
            return '<div class="hero-split-video">' . str_replace('{{iframe-title}}', 'Hero video', $embed) . '</div>';
        }

        if ($bgRaw !== '') {
            return '<div class="hero-split-media-bg" style="background-image: '
                . $this->cssUrl($this->mediaUrl($bgRaw)) . '" role="img" aria-hidden="true"></div>';
        }

        return '<div class="hero-split-media-fallback" aria-hidden="true"></div>';
    }

    /** @param array<string, mixed> $data */
    private function renderQuote(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $quote = (string) ($data['quote'] ?? $data['text'] ?? '');
        $cite = (string) ($data['cite'] ?? '');
        $note = (string) ($data['note'] ?? '');

        $motion = $this->motionFragment($attrs, $data);

        return '<section class="quote-block' . $motion['extraClass'] . '"' . $id . $motion['dataAttr'] . '>'
            . ($quote ? '<blockquote>' . $this->inline($quote) . '</blockquote>' : '')
            . ($cite ? '<cite>' . $this->inline($cite) . '</cite>' : '')
            . ($note ? '<p class="quote-note">' . $this->inline($note) . '</p>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderTrophies(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? 'Archive trophies');
        $intro = (string) ($data['intro'] ?? '');

        $feature = '';
        if (!empty($data['feature']) && is_array($data['feature'])) {
            $f = $data['feature'];
            $feature = '<figure class="trophy-feature">'
                . '<img src="' . $this->esc($this->asset((string) ($f['img'] ?? ''))) . '" alt="' . $this->esc((string) ($f['alt'] ?? '')) . '" loading="lazy" />'
                . '<figcaption>' . $this->inline((string) ($f['caption'] ?? '')) . '</figcaption></figure>';
        }

        $grid = '';
        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $grid .= '<figure class="trophy-item">'
                    . '<img src="' . $this->esc($this->asset((string) ($item['img'] ?? ''))) . '" alt="' . $this->esc((string) ($item['alt'] ?? '')) . '" loading="lazy" />'
                    . '<figcaption>' . $this->inline((string) ($item['caption'] ?? '')) . '</figcaption></figure>';
            }
        }

        return '<section class="trophy-case"' . $id . '>'
            . '<h2>' . $this->esc($title) . '</h2>'
            . ($intro ? '<p class="trophy-intro">' . $this->inline($intro) . '</p>' : '')
            . $feature
            . ($grid ? '<div class="trophy-grid">' . $grid . '</div>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderBadges(array $data, array $attrs, callable $renderMarkdown): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $row = '';
        if (!empty($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $img) {
                $row .= '<img src="' . $this->esc($this->asset((string) $img)) . '" alt="" width="120" height="120" aria-hidden="true" />';
            }
        }
        $body = trim((string) ($data['body'] ?? ''));
        if (!$body && !empty($data['copy'])) {
            $body = (string) $data['copy'];
        }

        return '<section class="badge-callout"' . $id . '>'
            . ($title ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . ($row ? '<div class="badge-row">' . $row . '</div>' : '')
            . ($body ? $renderMarkdown($body) : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderWiki(array $data, array $attrs, callable $renderMarkdown): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $lede = (string) ($data['lede'] ?? '');
        $debate = (string) ($data['debate'] ?? $data['body'] ?? '');
        $note = (string) ($data['note'] ?? '');

        return '<section class="wikipedia-erasure"' . $id . '>'
            . ($title ? '<h2>' . $this->inline($title) . '</h2>' : '')
            . ($lede ? '<p>' . $this->inline($lede) . '</p>' : '')
            . ($debate ? '<div class="wiki-debate">' . $renderMarkdown($debate) . '</div>' : '')
            . ($note ? '<p class="archive-note">' . $this->inline($note) . '</p>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderProfile(array $data, array $attrs, callable $renderMarkdown): string
    {
        $id = $this->attrId($attrs);
        $avatar = $this->asset((string) ($data['avatar'] ?? 'brian-teeman-huh-avatar.png'));
        $cover = $this->asset((string) ($data['cover'] ?? 'brian-teeman-cover.png'));
        $title = (string) ($data['title'] ?? '');
        $caption = (string) ($data['caption'] ?? '');
        $lede = (string) ($data['lede'] ?? '');
        $figcaption = (string) ($data['figcaption'] ?? '');
        $tags = (string) ($data['tags'] ?? '');
        $award = (string) ($data['award'] ?? '');
        $punch = (string) ($data['punch'] ?? '');
        $body = trim((string) ($data['body'] ?? ''));

        $html = '<section class="teeman-trail"' . $id . '>'
            . '<div class="teeman-header-row">'
            . '<figure class="teeman-avatar-circle" aria-label="Profile avatar">'
            . '<img src="' . $this->esc($avatar) . '" alt="' . $this->esc((string) ($data['avatar-alt'] ?? '')) . '" width="120" height="120" loading="lazy" />'
            . '</figure><div class="teeman-header-copy">'
            . ($title ? '<h2>' . $this->inline($title) . '</h2>' : '')
            . ($caption ? '<p class="teeman-avatar-caption">' . $this->inline($caption) . '</p>' : '')
            . '</div></div>'
            . ($lede ? '<p class="teeman-lede">' . $this->inline($lede) . '</p>' : '')
            . '<figure class="teeman-expose"><img src="' . $this->esc($cover) . '" alt="' . $this->esc((string) ($data['cover-alt'] ?? '')) . '" loading="lazy" />'
            . ($figcaption ? '<figcaption>' . $this->inline($figcaption)
                . ($tags ? '<span class="expose-tags">' . $this->esc($tags) . '</span>' : '')
                . '</figcaption>' : '')
            . '</figure>';

        if ($award || $punch) {
            $html .= '<div class="teeman-callout teeman-callout-award">';
            if ($award) {
                $html .= '<p>' . $this->inline($award) . '</p>';
            }
            if ($punch) {
                $html .= '<p class="callout-punch">' . $this->inline($punch) . '</p>';
            }
            $html .= '</div>';
        }

        if ($body) {
            $bodyHtml = $renderMarkdown($body);
            $bodyHtml = preg_replace('/<h3>/', '<h3 class="teeman-subhead">', $bodyHtml, 1) ?? $bodyHtml;
            $bodyHtml = preg_replace('/<p>(Image saved locally[^<]*)<\/p>/', '<p class="archive-note">$1</p>', $bodyHtml) ?? $bodyHtml;
            $html .= $bodyHtml;
        }

        return $html . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderGuestbook(array $data, array $attrs, callable $renderMarkdown): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $lede = (string) ($data['lede'] ?? '');
        $entries = (string) ($data['entries'] ?? '');
        $shot = $this->asset((string) ($data['screenshot'] ?? 'guestbook-deleted-thrice.png'));
        $shotAlt = (string) ($data['screenshot-alt'] ?? '');
        $shotCap = (string) ($data['screenshot-caption'] ?? '');
        $note = (string) ($data['note'] ?? '');

        return '<section class="guestbook-erasure"' . $id . '>'
            . ($title ? '<h2>' . $this->inline($title) . '</h2>' : '')
            . ($lede ? '<p>' . $this->inline($lede) . '</p>' : '')
            . ($entries ? '<div class="guestbook-old-copy">' . $renderMarkdown($entries) . '</div>' : '')
            . '<figure class="guestbook-proof"><img src="' . $this->esc($shot) . '" alt="' . $this->esc($shotAlt) . '" loading="lazy" />'
            . ($shotCap ? '<figcaption>' . $this->inline($shotCap) . '</figcaption>' : '')
            . '</figure>'
            . ($note ? '<p class="guestbook-note">' . $this->inline($note) . '</p>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderCards(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $cards = '';
        if (!empty($data['cards']) && is_array($data['cards'])) {
            foreach ($data['cards'] as $card) {
                if (!is_array($card)) {
                    continue;
                }
                $cards .= '<article class="card"><h3>' . $this->inline((string) ($card['title'] ?? '')) . '</h3>'
                    . '<p>' . $this->inline((string) ($card['body'] ?? '')) . '</p></article>';
            }
        }

        $motion = $this->motionFragment($attrs, $data, '');

        return '<section class="grid-section' . $motion['extraClass'] . '"' . $id . $motion['dataAttr'] . '>'
            . ($title ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . ($cards ? '<div class="cards">' . $cards . '</div>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderTimeline(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $items = '';
        if (!empty($data['events']) && is_array($data['events'])) {
            foreach ($data['events'] as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $items .= '<li><time>' . $this->esc((string) ($ev['date'] ?? '')) . '</time>'
                    . '<p>' . $this->inline((string) ($ev['text'] ?? '')) . '</p></li>';
            }
        }

        $motion = $this->motionFragment($attrs, $data);

        return '<section class="timeline' . $motion['extraClass'] . '"' . $id . $motion['dataAttr'] . '>'
            . ($title ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . ($items ? '<ol>' . $items . '</ol>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderCompare(array $data, array $attrs, callable $renderMarkdown): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $left = (string) ($data['left'] ?? 'Then');
        $right = (string) ($data['right'] ?? 'Now');
        $rows = '';
        if (!empty($data['rows']) && is_array($data['rows'])) {
            foreach ($data['rows'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $hl = !empty($row['highlight']) ? ' highlight' : '';
                $rows .= '<div class="compare-row' . $hl . '"><span>' . $this->inline((string) ($row['left'] ?? '')) . '</span>'
                    . '<span>' . $this->inline((string) ($row['right'] ?? '')) . '</span></div>';
            }
        }
        $note = (string) ($data['note'] ?? '');

        $motion = $this->motionFragment($attrs, $data);

        return '<section class="compare' . $motion['extraClass'] . '"' . $id . $motion['dataAttr'] . '>'
            . ($title ? '<h2>' . $this->inline($title) . '</h2>' : '')
            . '<div class="compare-table">'
            . '<div class="compare-head"><span>' . $this->esc($left) . '</span><span>' . $this->esc($right) . '</span></div>'
            . $rows . '</div>'
            . ($note ? '<p class="compare-note">' . $this->inline($note) . '</p>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderReceipts(array $data, array $attrs, callable $renderMarkdown): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $intro = (string) ($data['intro'] ?? '');
        $list = '';
        if (!empty($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as $link) {
                if (is_string($link)) {
                    $list .= '<li>' . $this->inline($link) . '</li>';
                }
            }
        }
        $body = trim((string) ($data['body'] ?? ''));
        if ($body && !$list) {
            return '<section class="receipts"' . $id . '><h2>' . $this->esc($title) . '</h2>' . $renderMarkdown($body) . '</section>';
        }

        return '<section class="receipts"' . $id . '>'
            . ($title ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . ($intro ? '<p class="receipts-intro">' . $this->inline($intro) . '</p>' : '')
            . ($list ? '<ul class="receipt-list">' . $list . '</ul>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderManifesto(array $data, array $attrs, callable $renderMarkdown): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $body = trim((string) ($data['body'] ?? ''));
        $signoff = (string) ($data['signoff'] ?? '');

        $motion = $this->motionFragment($attrs, $data);

        return '<section class="manifesto' . $motion['extraClass'] . '"' . $id . $motion['dataAttr'] . '>'
            . ($title ? '<h2>' . $this->inline($title) . '</h2>' : '')
            . ($body ? $renderMarkdown($body) : '')
            . ($signoff ? '<p class="sign-off">' . $this->safeHtml($signoff) . '</p>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderBlogTeaser(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $eyebrow = (string) ($data['eyebrow'] ?? 'Latest from the lab');
        $title = (string) ($data['title'] ?? '');
        $lede = (string) ($data['lede'] ?? '');
        $link = (string) ($data['link'] ?? '/blog/why-grav-mud');
        $linkLabel = (string) ($data['link-label'] ?? 'Read the post →');

        return '<section class="blog-teaser"' . $id . '><div class="blog-teaser-inner"><div class="blog-teaser-copy">'
            . '<p class="eyebrow">' . $this->esc($eyebrow) . '</p>'
            . ($title ? '<h2>' . $this->inline($title) . '</h2>' : '')
            . ($lede ? '<p>' . $this->inline($lede) . '</p>' : '')
            . '<div class="hero-actions">'
            . '<a class="btn primary" href="' . $this->esc($link) . '">' . $this->esc($linkLabel) . '</a>'
            . '<a class="btn ghost" href="/blog/">All posts</a>'
            . '</div></div></div></section>';
    }

    /** @param array<string, mixed> $data */
    private function renderBlogIndex(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $eyebrow = (string) ($data['eyebrow'] ?? '');
        $title = (string) ($data['title'] ?? '');
        $intro = (string) ($data['intro'] ?? '');
        $items = '';
        $posts = $data['posts'] ?? null;
        if (empty($posts) && !empty($data['post']) && is_array($data['post'])) {
            $posts = isset($data['post']['url']) || isset($data['post']['title']) ? [$data['post']] : $data['post'];
        }
        if (!empty($posts) && is_array($posts)) {
            foreach ($posts as $post) {
                if (!is_array($post)) {
                    continue;
                }
                $url = (string) ($post['url'] ?? '#');
                $items .= '<li class="blog-card"><article>'
                    . (!empty($post['date']) ? '<p class="blog-meta">' . $this->esc((string) $post['date']) . '</p>' : '')
                    . '<h2><a href="' . $this->esc($url) . '">' . $this->inline((string) ($post['title'] ?? '')) . '</a></h2>'
                    . '<p>' . $this->inline((string) ($post['excerpt'] ?? '')) . '</p>'
                    . '<a class="blog-read-more" href="' . $this->esc($url) . '">Read post →</a>'
                    . '</article></li>';
            }
        }

        return '<div class="blog-main"' . $id . '>'
            . '<header class="blog-index-header">'
            . ($eyebrow ? '<p class="eyebrow">' . $this->esc($eyebrow) . '</p>' : '')
            . ($title ? '<h1>' . $this->inline($title) . '</h1>' : '')
            . ($intro ? '<p class="blog-index-lede">' . $this->inline($intro) . '</p>' : '')
            . '</header>'
            . ($items ? '<ol class="blog-feed">' . $items . '</ol>' : '')
            . '</div>';
    }

    /** @param array<string, mixed> $data */
    private function renderBlogPostHeader(array $data, array $attrs): string
    {
        $back = (string) ($data['back'] ?? '/blog/');
        $date = (string) ($data['date'] ?? '');
        $title = (string) ($data['title'] ?? '');
        $dek = (string) ($data['dek'] ?? '');
        $byline = (string) ($data['byline'] ?? 'Grav MUD · FutureVision Labs');

        return '<div class="blog-main"><article class="blog-post">'
            . '<p class="blog-back"><a href="' . $this->esc($back) . '">← All posts</a></p>'
            . '<header class="blog-post-header">'
            . ($date ? '<p class="blog-meta">' . $this->esc($date) . ' · ' . $this->esc($byline) . '</p>' : '')
            . ($title ? '<h1>' . $this->inline($title) . '</h1>' : '')
            . ($dek ? '<p class="blog-dek">' . $this->inline($dek) . '</p>' : '')
            . '</header>';
    }

    /** @param array<string, mixed> $data */
    private function renderBlogBody(array $data, array $attrs, callable $renderMarkdown): string
    {
        $id = $this->attrId($attrs);
        $lede = (string) ($data['lede'] ?? '');
        $body = trim((string) ($data['body'] ?? ''));
        $html = '<section class="blog-post-body"' . $id . '>';
        if ($lede !== '') {
            $html .= '<p class="blog-lede">' . $this->inline($lede) . '</p>';
        }
        if ($body !== '') {
            $bodyHtml = $renderMarkdown($body);
            $bodyHtml = preg_replace('/<h3>/', '<h3 class="blog-subhead">', $bodyHtml) ?? $bodyHtml;
            $bodyHtml = preg_replace('/<p><strong>([^<]+)<\/strong><\/p>/', '<p class="trail-kicker"><strong>$1</strong></p>', $bodyHtml) ?? $bodyHtml;
            $html .= $bodyHtml;
        }

        return $html . '</section></article></div>';
    }

    /** @param array<string, mixed> $data */
    private function renderPricing(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $intro = (string) ($data['intro'] ?? '');
        $note = (string) ($data['note'] ?? '');
        $plans = '';

        if (!empty($data['plans']) && is_array($data['plans'])) {
            foreach ($data['plans'] as $plan) {
                if (!is_array($plan)) {
                    continue;
                }
                $featured = !empty($plan['highlight']) || !empty($plan['featured']);
                $features = '';
                $featRaw = (string) ($plan['features'] ?? '');
                if ($featRaw !== '') {
                    $lines = preg_split('/\r?\n/', trim($featRaw)) ?: [];
                    $features = '<ul class="pricing-features">';
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        $line = preg_replace('/^[-*]\s+/', '', $line) ?? $line;
                        $features .= '<li>' . $this->inline($line) . '</li>';
                    }
                    $features .= '</ul>';
                }
                $cta = !empty($plan['cta']) ? $this->renderBtn((string) $plan['cta']) : '';
                $plans .= '<article class="pricing-card' . ($featured ? ' pricing-card--featured' : '') . '">'
                    . (!empty($plan['badge']) ? '<span class="pricing-badge">' . $this->esc((string) $plan['badge']) . '</span>' : '')
                    . '<h3>' . $this->esc((string) ($plan['name'] ?? '')) . '</h3>'
                    . '<p class="pricing-price">' . $this->inline((string) ($plan['price'] ?? '')) . '</p>'
                    . ((string) ($plan['tagline'] ?? '') !== '' ? '<p class="pricing-tagline">' . $this->inline((string) $plan['tagline']) . '</p>' : '')
                    . $features
                    . ($cta ? '<div class="pricing-cta">' . $cta . '</div>' : '')
                    . '</article>';
            }
        }

        $motion = $this->motionFragment($attrs, $data);

        return '<section class="pricing' . $motion['extraClass'] . '"' . $id . $motion['dataAttr'] . '>'
            . ($title ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . ($intro ? '<p class="pricing-intro">' . $this->inline($intro) . '</p>' : '')
            . ($plans ? '<div class="pricing-grid">' . $plans . '</div>' : '')
            . ($note ? '<p class="pricing-note">' . $this->inline($note) . '</p>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderTheme(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? 'Theme');
        $desc = (string) ($data['desc'] ?? $data['description'] ?? '');
        $preset = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($data['preset'] ?? 'custom')));
        if ($preset === '') {
            $preset = 'custom';
        }

        $tokenMap = [];
        if (!empty($data['tokens']) && is_string($data['tokens'])) {
            foreach (preg_split('/\r?\n/', $data['tokens']) as $line) {
                if (preg_match('/^(\S+)\s+(.+)$/', trim($line), $m)) {
                    $tokenMap[$m[1]] = trim($m[2], "\"'");
                }
            }
        }

        $panelClass = 'theme-expo-panel theme-expo--' . $preset;
        $customCss = '';
        if ($tokenMap) {
            $rules = $this->buildTokenCss($tokenMap);
            if ($rules) {
                $customCss = '<style>' . $panelClass . ' { ' . preg_replace('/^\.mud-page\s*\{\s*|\s*\}$/', '', $rules) . ' }</style>';
            }
        }

        $swatches = '';
        $swatchKeys = $tokenMap ? array_keys($tokenMap) : ['bg', 'bg-card', 'text', 'muted', 'accent', 'teal', 'gold', 'border'];
        foreach ($swatchKeys as $key) {
            $var = '--' . preg_replace('/[^a-z0-9-]/', '-', strtolower($key));
            if ($key === 'bg-card' || $key === 'panel') {
                $var = '--bg-card';
            }
            $swatches .= '<div class="theme-expo-swatch"><span class="theme-expo-swatch-chip" style="background:var(' . $var . ')"></span>'
                . '<code>' . $this->esc($key) . '</code></div>';
        }

        return $customCss
            . '<article class="' . $panelClass . '"' . $id . '>'
            . '<header class="theme-expo-head"><h3>' . $this->esc($title) . '</h3>'
            . ($desc ? '<p>' . $this->inline($desc) . '</p>' : '')
            . ($preset !== 'custom' ? '<span class="theme-expo-preset">preset: <code>' . $this->esc($preset) . '</code></span>' : '')
            . '</header>'
            . '<div class="theme-expo-swatches">' . $swatches . '</div>'
            . '<div class="theme-expo-sample">'
            . '<p class="theme-expo-eyebrow">Eyebrow · sample</p>'
            . '<h4 class="theme-expo-title">Heading <span class="accent">Accent</span></h4>'
            . '<p class="theme-expo-lead">Lead copy on <code>--muted</code> — cards, buttons, and quotes inherit these tokens.</p>'
            . '<div class="theme-expo-actions"><span class="btn">Ghost</span><span class="btn primary">Primary</span></div>'
            . '<div class="theme-expo-mini-card"><strong>Card surface</strong><p>Uses <code>--bg-card</code> and <code>--border</code>.</p></div>'
            . '<blockquote class="theme-expo-quote">Theme in MUD = <code>@@@ tokens</code> + <code>grav-mud.css</code>.</blockquote>'
            . '</div></article>';
    }

    /** @param array<string, mixed> $data */
    private function renderVideo(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $caption = (string) ($data['caption'] ?? '');
        $aspect = (string) ($data['aspect'] ?? '16/9');
        if (!preg_match('/^\d+\s*\/\s*\d+$/', $aspect)) {
            $aspect = '16/9';
        }

        $embed = $this->buildVideoEmbed($data);
        if ($embed === '') {
            return '';
        }

        $iframeTitle = $title !== '' ? $title : 'Embedded video';

        $motion = $this->motionFragment($attrs, $data, '');

        return '<section class="mud-video' . $motion['extraClass'] . '"' . $id . $motion['dataAttr'] . '>'
            . ($title ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . '<div class="mud-video-frame" style="aspect-ratio:' . $this->esc(str_replace(' ', '', $aspect)) . '">'
            . str_replace('{{iframe-title}}', $this->esc($iframeTitle), $embed)
            . '</div>'
            . ($caption ? '<p class="mud-video-caption">' . $this->inline($caption) . '</p>' : '')
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function buildVideoEmbed(array $data): string
    {
        $src = trim((string) ($data['src'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));
        $youtube = trim((string) ($data['youtube'] ?? ''));
        $vimeo = trim((string) ($data['vimeo'] ?? ''));

        if ($src !== '') {
            return $this->renderNativeVideo($data, $src);
        }
        if ($youtube !== '') {
            $ytId = $this->extractYouTubeId($youtube);
            return $ytId ? $this->renderYouTubeEmbed($ytId, $data) : '';
        }
        if ($vimeo !== '') {
            $vmId = $this->extractVimeoId($vimeo);
            return $vmId ? $this->renderVimeoEmbed($vmId, $data) : '';
        }
        if ($url === '') {
            return '';
        }

        $ytId = $this->extractYouTubeId($url);
        if ($ytId) {
            return $this->renderYouTubeEmbed($ytId, $data);
        }
        $vmId = $this->extractVimeoId($url);
        if ($vmId) {
            return $this->renderVimeoEmbed($vmId, $data);
        }
        if (preg_match('#\.(mp4|webm|ogg|mov|m4v)(\?|#|$)#i', $url)) {
            return $this->renderNativeVideo($data, $url);
        }

        return '';
    }

    /** @param array<string, mixed> $data */
    private function renderNativeVideo(array $data, string $src): string
    {
        $videoUrl = $this->mediaUrl($src);
        if ($videoUrl === '') {
            return '';
        }

        $poster = trim((string) ($data['poster'] ?? ''));
        $posterAttr = $poster !== '' ? ' poster="' . $this->esc($this->mediaUrl($poster)) . '"' : '';
        $attrs = ['controls', 'playsinline', 'preload="metadata"'];
        if (!empty($data['autoplay'])) {
            $attrs[] = 'autoplay';
            $attrs[] = 'muted';
        }
        if (!empty($data['loop'])) {
            $attrs[] = 'loop';
        }

        $mime = $this->videoMimeType($videoUrl);
        $typeAttr = $mime ? ' type="' . $this->esc($mime) . '"' : '';

        return '<video class="mud-video-native" ' . implode(' ', $attrs) . $posterAttr . '>'
            . '<source src="' . $this->esc($videoUrl) . '"' . $typeAttr . '>'
            . 'Your browser does not support embedded video.'
            . '</video>';
    }

    /** @param array<string, mixed> $data */
    private function renderYouTubeEmbed(string $id, array $data): string
    {
        $start = (int) ($data['start'] ?? 0);
        $autoplay = !empty($data['autoplay']) ? '1' : '0';
        $watchUrl = 'https://www.youtube.com/watch?v=' . rawurlencode($id);
        $poster = 'https://i.ytimg.com/vi/' . $this->esc($id) . '/hqdefault.jpg';

        return '<div class="mud-youtube-facade" data-youtube-id="' . $this->esc($id) . '"'
            . ' data-youtube-start="' . $start . '"'
            . ' data-youtube-autoplay="' . $autoplay . '"'
            . ' data-youtube-title="{{iframe-title}}">'
            . '<button type="button" class="mud-youtube-play" aria-label="Play {{iframe-title}}">'
            . '<img class="mud-youtube-poster" src="' . $poster . '" alt="" loading="lazy" decoding="async" />'
            . '<span class="mud-youtube-play-icon" aria-hidden="true"></span>'
            . '</button>'
            . '<a class="mud-youtube-open" href="' . $this->esc($watchUrl) . '" target="_blank" rel="noopener noreferrer">Watch on YouTube</a>'
            . '<p class="mud-youtube-lan-note" hidden>LAN preview: YouTube blocks in-page embeds on IP addresses — tap play to open the app.</p>'
            . '</div>';
    }

    /** @param array<string, mixed> $data */
    private function renderVimeoEmbed(string $id, array $data): string
    {
        $params = ['dnt' => '1'];
        if (!empty($data['autoplay'])) {
            $params['autoplay'] = '1';
        }

        $query = http_build_query($params);
        $src = 'https://player.vimeo.com/video/' . rawurlencode($id);
        if ($query !== '') {
            $src .= '?' . $query;
        }
        $start = (int) ($data['start'] ?? 0);
        if ($start > 0) {
            $src .= '#t=' . $start . 's';
        }

        return '<iframe class="mud-video-embed mud-video-vimeo" src="' . $this->esc($src) . '" '
            . 'title="{{iframe-title}}" loading="lazy" allowfullscreen '
            . 'allow="autoplay; fullscreen; picture-in-picture" '
            . 'referrerpolicy="strict-origin-when-cross-origin"></iframe>';
    }

    private function extractYouTubeId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#(?:youtube\.com/(?:watch\?(?:[^&\s]+&)*v=|embed/|shorts/)|youtu\.be/)([a-zA-Z0-9_-]{11})#', $value, $m)) {
            return $m[1];
        }
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $value)) {
            return $value;
        }
        return '';
    }

    private function extractVimeoId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#(?:vimeo\.com/|player\.vimeo\.com/video/)(\d+)#', $value, $m)) {
            return $m[1];
        }
        if (preg_match('/^\d+$/', $value)) {
            return $value;
        }
        return '';
    }

    private function mediaUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (str_starts_with($path, '/')) {
            return $path;
        }
        return $this->asset($path);
    }

    private function videoMimeType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg', 'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            default => '',
        };
    }

    /** @param array<string, mixed> $data */
    private function renderGallery(array $data, array $attrs, string $body): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $cols = (string) ($attrs['columns'] ?? $data['columns'] ?? '3');
        $lightbox = $this->isTruthy($attrs['lightbox'] ?? $data['lightbox'] ?? false);
        $items = $this->parseImageList($body, $data);
        if (!$items) {
            return '';
        }

        $figures = '';
        foreach ($items as $item) {
            $src = $this->mediaUrl((string) ($item['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $caption = (string) ($item['caption'] ?? '');
            $alt = (string) ($item['alt'] ?? $caption);
            $img = '<img src="' . $this->esc($src) . '" alt="' . $this->esc($alt) . '" loading="lazy" />';
            $inner = $lightbox
                ? '<button type="button" class="mud-gallery-zoom" data-full="' . $this->esc($src) . '" data-caption="' . $this->esc($caption) . '">' . $img . '</button>'
                : $img;
            $figures .= '<figure class="mud-gallery-item">' . $inner
                . ($caption !== '' ? '<figcaption>' . $this->inline($caption) . '</figcaption>' : '')
                . '</figure>';
        }

        $motion = $this->motionFragment($attrs, $data);
        $classes = 'mud-gallery' . ($lightbox ? ' mud-gallery--lightbox' : '') . $motion['extraClass'];
        return '<section class="' . trim($classes) . '"' . $id . $motion['dataAttr']
            . ' style="--mud-gallery-cols:' . $this->esc($cols) . '">'
            . ($title !== '' ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . '<div class="mud-gallery-grid">' . $figures . '</div>'
            . '</section>';
    }

    /** @param array<string, mixed> $data */
    private function renderCarousel(array $data, array $attrs, string $body): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $autoplay = (int) ($attrs['autoplay'] ?? $data['autoplay'] ?? 0);
        $aspect = (string) ($attrs['aspect'] ?? $data['aspect'] ?? '16/9');
        $variant = strtolower((string) ($attrs['variant'] ?? $data['variant'] ?? 'flat'));
        $is3d = in_array($variant, ['3d', 'coverflow', 'cover'], true);
        $items = $this->parseImageList($body, $data);
        if (!$items) {
            return '';
        }

        $slides = '';
        $dots = '';
        $i = 0;
        foreach ($items as $item) {
            $src = $this->mediaUrl((string) ($item['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $caption = (string) ($item['caption'] ?? '');
            $alt = (string) ($item['alt'] ?? $caption);
            $active = $i === 0 ? ' is-active' : '';
            $slides .= '<li class="mud-carousel-slide' . $active . '" id="mud-carousel-slide-' . $i . '">'
                . '<figure><img src="' . $this->esc($src) . '" alt="' . $this->esc($alt) . '" loading="lazy" />'
                . ($caption !== '' ? '<figcaption>' . $this->inline($caption) . '</figcaption>' : '')
                . '</figure></li>';
            $dots .= '<button type="button" class="mud-carousel-dot' . $active . '" data-slide="' . $i . '" aria-label="Slide ' . ($i + 1) . '"' . ($i === 0 ? ' aria-current="true"' : '') . '></button>';
            $i++;
        }
        if ($slides === '') {
            return '';
        }

        $motion = $this->motionFragment($attrs, $data);
        $carouselClass = 'mud-carousel' . ($is3d ? ' mud-carousel--3d' : '') . $motion['extraClass'];

        return '<section class="' . trim($carouselClass) . '"' . $id . $motion['dataAttr']
            . ' data-autoplay="' . $autoplay . '" data-variant="' . $this->esc($is3d ? '3d' : 'flat') . '"'
            . ' style="--mud-carousel-aspect:' . $this->esc($aspect) . '">'
            . ($title !== '' ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . '<div class="mud-carousel-viewport">'
            . '<button type="button" class="mud-carousel-prev" aria-label="Previous slide">&#8249;</button>'
            . '<ol class="mud-carousel-track">' . $slides . '</ol>'
            . '<button type="button" class="mud-carousel-next" aria-label="Next slide">&#8250;</button>'
            . '</div>'
            . '<div class="mud-carousel-dots" role="tablist">' . $dots . '</div>'
            . '</section>';
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{src: string, caption?: string, alt?: string}>
     */
    private function parseImageList(string $body, array $data): array
    {
        $items = [];
        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $src = (string) ($item['src'] ?? $item['img'] ?? $item['image'] ?? '');
                if ($src === '') {
                    continue;
                }
                $items[] = [
                    'src' => $src,
                    'caption' => (string) ($item['caption'] ?? $item['title'] ?? ''),
                    'alt' => (string) ($item['alt'] ?? ''),
                ];
            }
        }
        if ($items) {
            return $items;
        }

        $lines = [];
        if (!empty($data['_lines']) && is_array($data['_lines'])) {
            $lines = $data['_lines'];
        } else {
            $lines = preg_split('/\r?\n/', $body) ?: [];
        }

        foreach ($lines as $line) {
            if (!preg_match('/^\s*-\s+(.+)$/', $line, $m)) {
                continue;
            }
            $parts = array_map('trim', explode('|', $m[1], 2));
            $src = $parts[0] ?? '';
            if ($src === '') {
                continue;
            }
            $items[] = [
                'src' => $src,
                'caption' => $parts[1] ?? '',
            ];
        }

        return $items;
    }

    private function isTruthy(mixed $val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        $s = strtolower(trim((string) $val));
        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }

    /** @param array<string, mixed> $data */
    private function renderCode(array $data, array $attrs): string
    {
        $id = $this->attrId($attrs);
        $title = (string) ($data['title'] ?? '');
        $lang = $this->esc((string) ($data['lang'] ?? 'mud'));
        $body = trim((string) ($data['body'] ?? ''));
        $caption = (string) ($data['caption'] ?? '');

        return '<section class="code-block"' . $id . '>'
            . ($title ? '<h2>' . $this->esc($title) . '</h2>' : '')
            . '<figure class="code-sample">'
            . '<figcaption class="code-lang">' . $lang . '</figcaption>'
            . '<pre><code>' . $this->esc($body) . '</code></pre>'
            . ($caption ? '<p class="code-caption">' . $this->inline($caption) . '</p>' : '')
            . '</figure></section>';
    }

    private function renderBtn(string $raw): string
    {
        $variant = 'ghost';
        if (preg_match('/\s+primary\s*$/i', $raw)) {
            $variant = 'primary';
            $raw = preg_replace('/\s+primary\s*$/i', '', $raw) ?? $raw;
        }
        if (!preg_match('/^\[([^\]]+)\]\(([^)]+)\)/', trim($raw), $m)) {
            return '';
        }
        return '<a class="btn ' . $variant . '" href="' . $this->esc($m[2]) . '">' . $this->esc($m[1]) . '</a>';
    }

    /**
     * Parse design-spec bodies: key: val, nested blocks (feature:, item:, card:), lists.
     * @return array<string, mixed>
     */
    public function parseStructuredBody(string $body): array
    {
        $data = [];
        $lines = preg_split('/\r?\n/', $body) ?: [];
        $i = 0;
        $n = count($lines);
        $blockKey = null;
        $blockBuf = [];

        $flushBlock = function () use (&$data, &$blockKey, &$blockBuf) {
            if ($blockKey === null) {
                return;
            }
            $parsed = $this->parseStructuredBody(trim(implode("\n", $blockBuf)));
            if (in_array($blockKey, ['item', 'card', 'event', 'row', 'plan', 'post'], true)) {
                $plural = $blockKey === 'item' ? 'items' : ($blockKey === 'card' ? 'cards' : ($blockKey === 'event' ? 'events' : ($blockKey === 'row' ? 'rows' : ($blockKey === 'post' ? 'posts' : 'plans'))));
                if (!isset($data[$plural]) || !is_array($data[$plural])) {
                    $data[$plural] = [];
                }
                $data[$plural][] = $parsed;
            } elseif ($blockKey === 'feature') {
                $data['feature'] = $parsed;
            } else {
                $data[$blockKey] = $parsed;
            }
            $blockKey = null;
            $blockBuf = [];
        };

        while ($i < $n) {
            $line = $lines[$i];
            $trim = trim($line);

            if (preg_match('/^(feature|item|card|event|row|plan|post):\s*$/i', $trim, $bm)) {
                $flushBlock();
                $blockKey = strtolower($bm[1]);
                $blockBuf = [];
                $i++;
                continue;
            }

            if ($blockKey !== null) {
                if ($trim === '' && $blockBuf) {
                    $flushBlock();
                    $i++;
                    continue;
                }
                if ($trim !== '' && preg_match('/^[a-z][\w-]*:\s*$/i', $trim)) {
                    $flushBlock();
                    continue;
                }
                $blockBuf[] = $line;
                $i++;
                continue;
            }

            if (preg_match('/^\s*(\w[\w-]*):\s*(.*)$/', $line, $m)) {
                $key = $m[1];
                $val = trim($m[2]);
                if ($val === '|' || $val === '>') {
                    $i++;
                    $buf = [];
                    while ($i < $n) {
                        $next = $lines[$i];
                        if (preg_match('/^\s*(\w[\w-]*):\s*/', $next) && !preg_match('/^\s{2,}/', $next)) {
                            break;
                        }
                        if (preg_match('/^(feature|item|card|event|row|plan|post):\s*$/i', trim($next))
                            && !preg_match('/^\s{2,}/', $next)) {
                            break;
                        }
                        $buf[] = preg_replace('/^\s{2}/', '', $next) ?? $next;
                        $i++;
                    }
                    $data[$key] = trim(implode("\n", $buf));
                    continue;
                }
                if ($val === '') {
                    $listKey = $key;
                    $data[$listKey] = [];
                    $i++;
                    while ($i < $n && preg_match('/^\s+-\s+(.*)$/', $lines[$i], $li)) {
                        $data[$listKey][] = $li[1];
                        $i++;
                    }
                    continue;
                }
                $data[$key] = $val;
                $i++;
                continue;
            }

            if (preg_match('/^\s+-\s+(.*)$/', $line, $li)) {
                if (!isset($data['_lines'])) {
                    $data['_lines'] = [];
                }
                $data['_lines'][] = $li[1];
            }

            $i++;
        }

        $flushBlock();
        return $data;
    }

    private function asset(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $path = preg_replace('#^/assets/#', '', $path) ?? $path;
        $path = ltrim($path, '/');
        return $this->assetBase . $path;
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array<string, mixed> $data
     * @return array{extraClass: string, dataAttr: string}
     */
    private function motionFragment(array $attrs, array $data, string $pageDefault = 'fade-up'): array
    {
        $animate = $this->resolveAnimate($attrs, $data, $pageDefault);
        if ($animate === '') {
            return ['extraClass' => '', 'dataAttr' => ''];
        }

        return [
            'extraClass' => ' mud-reveal',
            'dataAttr' => ' data-animate="' . $this->esc($animate) . '"',
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array<string, mixed> $data
     */
    private function resolveAnimate(array $attrs, array $data, string $pageDefault = 'fade-up'): string
    {
        $raw = trim((string) ($attrs['animate'] ?? $data['animate'] ?? ''));
        if ($raw !== '') {
            return $this->sanitizeAnimate($raw);
        }
        if ($this->pageMotion === 'on' && $pageDefault !== '') {
            return $this->sanitizeAnimate($pageDefault);
        }

        return '';
    }

    private function sanitizeAnimate(string $name): string
    {
        $name = strtolower(preg_replace('/[^a-z0-9-]/', '', $name) ?? '');
        $allowed = ['fade-up', 'fade-in', 'reveal-left', 'stagger', 'glow-pulse', 'typewriter'];

        return in_array($name, $allowed, true) ? $name : '';
    }

    /** Safe url() for HTML style="" attributes (outer attr uses double quotes). */
    private function cssUrl(string $resolvedUrl): string
    {
        if ($resolvedUrl === '') {
            return 'none';
        }

        return "url('" . $this->esc($resolvedUrl) . "')";
    }

    /** @param list<string> $pairs */
    private function inlineStyle(array $pairs): string
    {
        if ($pairs === []) {
            return '';
        }

        return ' style="' . implode('; ', $pairs) . '"';
    }

    /** @param array<string, string> $attrs */
    private function attrId(array $attrs): string
    {
        return !empty($attrs['id']) ? ' id="' . $this->esc($attrs['id']) . '"' : '';
    }

    private function inline(string $text): string
    {
        $text = $this->esc($text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text) ?? $text;
        return $text;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Trusted design-spec HTML (signoff only). */
    private function safeHtml(string $text): string
    {
        return strip_tags($text, '<br><br/><strong><em><span>');
    }
}
