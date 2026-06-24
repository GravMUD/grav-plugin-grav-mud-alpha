<?php



namespace Grav\Plugin\GravMudAlpha;

use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;

/**

 * Grav MUD Alpha — MarkUpDown (.mud) compiler (PHP).

 * v0.3: Design Spec fences, @@@ tokens, Lone Mamber layout primitives.

 */

class MudAlphaCompiler

{

    private ?MudDesignSpec $design = null;

    private bool $designOpen = false;

    private string $assetBase = '';

    private string $commentApiBase = '/api/mud-commentz';

    private string $messengerApiBase = '/api/mud-messenger';

    private string $marketplaceRoute = 'shop';

    private string $flipzineBase = 'https://flipzine.live';

    private string $flipzineDefaultSlug = 'gwenbot-guide';

    private string $swagRoute = 'shop';

    private ?Grav $grav = null;

    public function setGrav(Grav $grav): void
    {
        $this->grav = $grav;
    }

    public function compile(string $source): string

    {

        $parsed = $this->parseSource($source);

        $html = $this->renderNodes($parsed['nodes']);

        if ($this->designOpen && $this->getDesign()) {

            $html .= $this->getDesign()->renderDesignClose();

            $this->designOpen = false;

        }

        return $html;

    }



    public function setAssetBase(string $base): void

    {

        $this->assetBase = rtrim($base, '/') . '/';

        $this->getDesign()->setAssetBase($this->assetBase);

    }

    public function setCommentApiBase(string $base): void
    {
        $this->commentApiBase = '/' . trim($base, '/');
    }

    public function setMessengerApiBase(string $base): void
    {
        $this->messengerApiBase = '/' . trim($base, '/');
    }

    public function setMarketplaceRoute(string $route): void
    {
        $this->marketplaceRoute = trim($route, '/') ?: 'shop';
    }

    public function setFlipzineBase(string $base): void
    {
        $base = rtrim(trim($base), '/');
        $this->flipzineBase = $base !== '' ? $base : 'https://flipzine.live';
    }

    public function setFlipzineDefaultSlug(string $slug): void
    {
        $slug = trim($slug);
        $this->flipzineDefaultSlug = $slug !== '' ? $slug : 'gwenbot-guide';
    }

    public function setSwagRoute(string $route): void
    {
        $this->swagRoute = trim($route, '/') ?: 'shop';
    }

    /** Compile a snippet for spec examples (nested fences). */
    public function compileSnippet(string $source): string
    {
        $compiler = new self();
        if ($this->grav !== null) {
            $compiler->setGrav($this->grav);
        }
        if ($this->assetBase !== '') {
            $compiler->setAssetBase($this->assetBase);
        }
        if ($this->commentApiBase !== '/api/mud-commentz') {
            $compiler->setCommentApiBase($this->commentApiBase);
        }
        if ($this->messengerApiBase !== '/api/mud-messenger') {
            $compiler->setMessengerApiBase($this->messengerApiBase);
        }
        if ($this->marketplaceRoute !== 'shop') {
            $compiler->setMarketplaceRoute($this->marketplaceRoute);
        }
        if ($this->flipzineBase !== 'https://flipzine.live') {
            $compiler->setFlipzineBase($this->flipzineBase);
        }
        if ($this->flipzineDefaultSlug !== 'gwenbot-guide') {
            $compiler->setFlipzineDefaultSlug($this->flipzineDefaultSlug);
        }
        if ($this->swagRoute !== 'shop') {
            $compiler->setSwagRoute($this->swagRoute);
        }
        return $compiler->compile($source);
    }



    private function getDesign(): MudDesignSpec

    {

        if ($this->design === null) {

            require_once __DIR__ . '/MudDesignSpec.php';

            $this->design = new MudDesignSpec();

        }

        return $this->design;

    }



    /** @return array{frontmatter: array<string, mixed>, nodes: list<array<string, mixed>>} */

    private function parseSource(string $source): array

    {

        $frontmatter = [];

        $body = $source;



        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n/s', $source, $m)) {

            $frontmatter = $this->parseYamlLite($m[1]);

            $body = substr($source, strlen($m[0]));

        }



        return [

            'frontmatter' => $frontmatter,

            'nodes' => $this->parseBlocks(trim($body)),

        ];

    }



    /** @return array<string, mixed> */

    private function parseYamlLite(string $block): array

    {

        $data = [];

        foreach (preg_split('/\r?\n/', $block) as $line) {

            if (!preg_match('/^(\w+):\s*(.*)$/', trim($line), $m)) {

                continue;

            }

            $val = trim($m[2], " \t\"'");

            $data[$m[1]] = $val;

        }

        return $data;

    }



    /** @return list<array<string, mixed>> */

    private function parseBlocks(string $text): array

    {

        $lines = preg_split('/\r?\n/', $text) ?: [];

        $nodes = [];

        $mdBuf = [];

        $i = 0;

        $count = count($lines);



        $flushMd = function () use (&$mdBuf, &$nodes) {

            if ($mdBuf) {

                $nodes[] = ['type' => 'markdown', 'content' => trim(implode("\n", $mdBuf))];

                $mdBuf = [];

            }

        };



        while ($i < $count) {

            $line = $lines[$i];

            $trim = trim($line);



            if (preg_match('/^@@@\s*(.*)$/', $trim, $specHead)) {

                $flushMd();

                $i++;

                $body = [];

                while ($i < $count && trim($lines[$i]) !== '@@@') {

                    $body[] = $lines[$i];

                    $i++;

                }

                if ($i < $count) {

                    $i++;

                }

                $label = trim($specHead[1]);

                $bodyStr = trim(implode("\n", $body));

                if ($label !== '' && !preg_match('/^\w+:/', $bodyStr)) {

                    $bodyStr = "name: {$label}\n" . $bodyStr;

                }

                $nodes[] = ['type' => 'design-open', 'body' => $bodyStr];

                continue;

            }



            if (preg_match('/^\+\+\s*(.*)$/', $trim, $exec)) {

                $flushMd();

                $i++;

                $body = [];

                while ($i < $count && !$this->isBoundary($lines[$i])) {

                    $body[] = $lines[$i];

                    $i++;

                }

                $nodes[] = [

                    'type' => 'exec',

                    'label' => trim($exec[1]),

                    'body' => trim(implode("\n", $body)),

                ];

                continue;

            }



            if (preg_match('/^:::\s*\S/', $trim)) {

                $flushMd();

                $header = $this->parseFenceHeader($line);

                $i++;

                $body = [];

                $fenceDepth = 1;

                while ($i < $count) {

                    $innerTrim = trim($lines[$i]);

                    if (preg_match('/^:::\s*\S/', $innerTrim)) {

                        $fenceDepth++;

                        $body[] = $lines[$i];

                        $i++;

                        continue;

                    }

                    if ($innerTrim === ':::') {

                        if ($fenceDepth > 1) {

                            $fenceDepth--;

                            $body[] = $lines[$i];

                            $i++;

                            continue;

                        }

                        break;

                    }

                    $body[] = $lines[$i];

                    $i++;

                }

                if ($i < $count) {

                    $i++;

                }

                $bodyStr = implode("\n", $body);

                $nodes[] = [

                    'type' => 'fence',

                    'fenceType' => $header['fenceType'],

                    'attrs' => $header['attrs'],

                    'body' => $bodyStr,

                    'children' => $this->parseBlocks($bodyStr),

                ];

                continue;

            }



            $mdBuf[] = $line;

            $i++;

        }



        $flushMd();

        return $nodes;

    }



    private function isBoundary(string $line): bool

    {

        $t = trim($line);

        return (bool) preg_match('/^\+\+\s/', $t)

            || (bool) preg_match('/^:::\s*\S/', $t)

            || (bool) preg_match('/^@@@/', $t)

            || (bool) preg_match('/^#{1,6}\s+/', $t);

    }



    /** @return array{fenceType: string, attrs: array<string, string>} */

    private function parseFenceHeader(string $line): array

    {

        $inner = trim(preg_replace('/^:::\s*/', '', $line) ?? '');

        $attrs = [];

        $fenceType = 'unknown';

        $tail = $inner;

        if (preg_match('/^([\w-]+)\{([^}]*)\}(.*)$/', $inner, $m)) {

            $fenceType = strtolower($m[1]);

            $this->mergeFenceAttrs($m[2], $attrs);

            $tail = trim($m[3]);

        } else {

            $parts = preg_split('/\s+/', $inner, 2) ?: [];

            $fenceType = strtolower($parts[0] ?? 'unknown');

            $tail = trim($parts[1] ?? '');

        }

        if ($tail !== '') {

            $this->mergeFenceAttrs($tail, $attrs);

            $tokens = preg_split('/\s+/', $tail) ?: [];

            for ($j = 0, $n = count($tokens); $j < $n; $j++) {

                $token = $tokens[$j];

                if (strpos($token, '=') !== false || str_contains($token, '{')) {

                    continue;

                }

                if (!isset($attrs['variant'])) {

                    $attrs['variant'] = $token;

                }

            }

        }

        return ['fenceType' => $fenceType, 'attrs' => $attrs];

    }



    /** @param array<string, string> $attrs */

    private function mergeFenceAttrs(string $raw, array &$attrs): void

    {

        if (!preg_match_all('/([\w-]+)=(?:"([^"]*)"|\'([^\']*)\'|(\S+))/', $raw, $matches, PREG_SET_ORDER)) {

            return;

        }

        foreach ($matches as $match) {

            $attrs[$match[1]] = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);

        }

    }



    /** @param list<array<string, mixed>> $nodes */

    private function renderNodes(array $nodes): string

    {

        $out = [];

        foreach ($nodes as $node) {

            $out[] = $this->renderNode($node);

        }

        return implode("\n", array_filter($out));

    }



    /** @param array<string, mixed> $node */

    private function renderNode(array $node): string

    {

        $type = $node['type'] ?? '';

        if ($type === 'markdown') {

            return $this->renderMarkdown((string) ($node['content'] ?? ''));

        }

        if ($type === 'exec') {

            $label = $this->esc((string) ($node['label'] ?? 'exec'));

            $body = $this->esc((string) ($node['body'] ?? ''));

            return '<pre class="mud-exec mud-non" data-label="' . $label . '"><code>' . $body . '</code></pre>';

        }

        if ($type === 'design-open') {

            $spec = $this->getDesign()->parseStructuredBody((string) ($node['body'] ?? ''));

            if (!empty($spec['tokens']) && is_string($spec['tokens'])) {

                $spec['tokens'] = $this->parseTokenLines($spec['tokens']);

            }

            $this->designOpen = true;

            return $this->getDesign()->renderDesignOpen($spec);

        }

        if ($type === 'fence') {

            return $this->renderFence($node);

        }

        return '';

    }



    /** @return array<string, string> */

    private function parseTokenLines(string $block): array

    {

        $tokens = [];

        foreach (preg_split('/\r?\n/', $block) as $line) {

            if (preg_match('/^(\S+)\s+(.+)$/', trim($line), $m)) {

                $tokens[$m[1]] = trim($m[2], "\"'");

            }

        }

        return $tokens;

    }



    /** @param array<string, mixed> $node */

    private function renderFence(array $node): string

    {

        $t = strtolower((string) ($node['fenceType'] ?? ''));

        $body = (string) ($node['body'] ?? '');

        $attrs = $node['attrs'] ?? [];

        $children = $node['children'] ?? [];

        if (in_array($t, ['example', 'demo', 'spec-example'], true)) {
            return $this->renderExample($node);
        }

        $design = $this->getDesign();

        $designTypes = [

            'spec', 'design', 'hero', 'spec-hero', 'quote', 'spec-quote',

            'trophies', 'spec-trophies', 'badges', 'spec-badges',

            'wiki', 'spec-wiki', 'teeman', 'profile', 'spec-profile',

            'teeman-meme', 'spec-teeman-meme',

            'guestbook', 'spec-guestbook', 'cards', 'spec-cards',

            'timeline', 'spec-timeline', 'compare', 'spec-compare',

            'receipts', 'spec-receipts', 'manifesto', 'spec-manifesto',

            'blog-teaser', 'spec-blog-teaser', 'blog-index', 'spec-blog-index',

            'blog-post-header', 'spec-blog-post-header', 'blog-body', 'spec-blog-body',

            'pricing', 'spec-pricing', 'code', 'spec-code',

            'video', 'spec-video',

            'theme', 'spec-theme',

            'gallery', 'spec-gallery', 'carousel', 'spec-carousel',

        ];



        if (in_array($t, $designTypes, true)) {

            $specNode = array_merge($node, [

                'attrs' => $attrs,

                'body' => $body,

            ]);

            $html = $design->renderFence(

                $specNode,

                fn (array $kids) => $this->renderNodes($kids),

                fn (string $md) => $this->renderMarkdown($md)

            );

            if ($html !== '') {

                return $html;

            }

        }



        $data = $this->parseKeyValueBody($body);



        switch ($t) {

            case 'callout':

                $variant = $attrs['variant'] ?? 'note';

                return '<aside class="mud-callout mud-callout--' . $this->esc($variant) . '">'

                    . $this->renderMarkdown(trim($body))

                    . '</aside>';



            case 'ticker':

                return $this->renderTicker($attrs, $data, $body);



            case 'section':

                $sectionData = $this->parseKeyValueBody($body);
                $sectionId = (string) ($attrs['id'] ?? $sectionData['id'] ?? '');
                $id = $sectionId !== '' ? ' id="' . $this->esc($sectionId) . '"' : '';

                if (!empty($sectionData['body'])) {
                    $inner = $this->renderMarkdown(trim((string) $sectionData['body']));
                } elseif ($children) {
                    $inner = $this->renderNodes($children);
                } else {
                    $inner = $this->renderMarkdown(trim($body));
                }

                return '<section class="mud-section"' . $id . '>' . $inner . '</section>';



            case 'card':

                return '<article class="mud-card">'

                    . (!empty($data['title']) ? '<h3>' . $this->inlineMd((string) $data['title']) . '</h3>' : '')

                    . (!empty($data['body']) ? '<div class="mud-card-body">' . $this->renderMarkdown((string) $data['body']) . '</div>' : '')

                    . '</article>';



            case 'commentz':

            case 'feed':

                return $this->renderCommentzEmbed($attrs);



            case 'marketplace':

            case 'bazaar':

            case 'shop':

                return $this->renderMarketplaceEmbed($attrs);



            case 'flipzine':

            case 'zine':

            case 'flipbook':

                return $this->renderFlipzineEmbed($attrs);



            case 'swag':

            case 'printify':

            case 'pod':

            case 'merch':

                return $this->renderSwagEmbed($attrs);



            case 'messenger':

            case 'chat':

            case 'chatbox':

            case 'mud-messenger':

                return $this->renderMessengerEmbed($attrs);



            default:
                if ($this->grav !== null) {
                    $event = new Event([
                        'type' => $t,
                        'node' => $node,
                        'attrs' => $attrs,
                        'body' => $body,
                        'data' => $data,
                        'children' => $children,
                        'html' => null,
                        'renderChildren' => function () use ($children): string {
                            return $children ? $this->renderNodes($children) : '';
                        },
                        'renderMarkdown' => function (string $md): string {
                            return $this->renderMarkdown($md);
                        },
                    ]);
                    $this->grav->fireEvent('onMudFenceRender', $event);
                    $pluginHtml = $event['html'] ?? null;
                    if (is_string($pluginHtml) && $pluginHtml !== '') {
                        return $pluginHtml;
                    }
                }

                return '<div class="mud-fence mud-fence--' . $this->esc($t) . '">'

                    . ($children ? $this->renderNodes($children) : $this->renderMarkdown(trim($body)))

                    . '</div>';

        }

    }



    /** @param array<string, mixed> $node */
    private function renderExample(array $node): string
    {
        $data = $this->parseKeyValueBody((string) ($node['body'] ?? ''));
        $title = (string) ($data['title'] ?? 'Example');
        $id = (string) ($data['id'] ?? '');
        $desc = (string) ($data['desc'] ?? $data['description'] ?? '');
        $source = trim((string) ($data['body'] ?? ''));
        $caption = (string) ($data['caption'] ?? '');

        if ($source === '') {
            return '';
        }

        if (!preg_match('/@@@/', $source)) {
            $source = "@@@\nname: grav-official\nlayout: promo\n@@@\n\n" . $source;
        }

        $preview = $this->compileSnippet($source);
        $idAttr = $id !== '' ? ' id="' . $this->esc($id) . '"' : '';
        $label = $id !== '' ? $id : $title;

        $html = '<article class="mud-spec-entry"' . $idAttr . '>'
            . '<div class="mud-spec-entry-head">'
            . '<span class="mud-spec-id">' . $this->esc($label) . '</span>'
            . '<h3 class="mud-spec-entry-title">' . $this->esc($title) . '</h3>'
            . '</div>';

        if ($desc !== '') {
            $html .= '<p class="mud-spec-desc">' . $this->inlineMd($desc) . '</p>';
        }

        $html .= '<div class="mud-spec-pair">'
            . '<div class="mud-spec-pane mud-spec-pane--source">'
            . '<span class="mud-spec-pane-label">.mud source</span>'
            . '<pre class="mud-spec-code"><code>' . $this->esc($source) . '</code></pre>'
            . '</div>'
            . '<div class="mud-spec-pane mud-spec-pane--output">'
            . '<span class="mud-spec-pane-label">HTML output</span>'
            . '<div class="mud-spec-preview">' . $preview . '</div>'
            . '</div>'
            . '</div>';

        if ($caption !== '') {
            $html .= '<p class="mud-spec-caption">' . $this->inlineMd($caption) . '</p>';
        }

        return $html . '</article>';
    }

    /** @return array<string, string|list<string>> */
    private function parseKeyValueBody(string $body): array
    {
        return $this->getDesign()->parseStructuredBody($body);
    }

    private function isMdTableRow(string $line): bool
    {
        return (bool) preg_match('/^\|.+\|$/', trim($line));
    }

    private function isMdTableSeparator(string $line): bool
    {
        return (bool) preg_match('/^\|[\s|:-]+\|$/', trim($line));
    }

    /** @return list<string> */
    private function parseMdTableCells(string $line): array
    {
        $inner = trim(trim($line), '|');
        return array_map('trim', explode('|', $inner));
    }

    private function renderMarkdown(string $content): string

    {

        if ($content === '') {

            return '';

        }

        $out = [];

        $inUl = false;

        $lines = preg_split('/\r?\n/', $content) ?: [];

        $n = count($lines);

        for ($i = 0; $i < $n; $i++) {

            $line = $lines[$i];

            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $h)) {

                if ($inUl) {

                    $out[] = '</ul>';

                    $inUl = false;

                }

                $level = strlen($h[1]);

                $title = $h[2];

                $idAttr = '';

                if (preg_match('/^(.+?)\s+\{#([a-z][\w-]*)\}\s*$/i', $title, $hm)) {

                    $title = $hm[1];

                    $idAttr = ' id="' . $this->esc($hm[2]) . '"';

                }

                $out[] = '<h' . $level . $idAttr . '>' . $this->inlineMd($title) . '</h' . $level . '>';

                continue;

            }

            if (preg_match('/^-\s+(.*)$/', $line, $li)) {

                if (!$inUl) {

                    $out[] = '<ul>';

                    $inUl = true;

                }

                $out[] = '<li>' . $this->inlineMd($li[1]) . '</li>';

                continue;

            }

            if ($inUl) {

                $out[] = '</ul>';

                $inUl = false;

            }

            if (!trim($line)) {

                continue;

            }

            if (preg_match('/^\s*<[^>]+>/', $line)) {

                $out[] = $line;

                continue;

            }

            if ($this->isMdTableRow($line) && ($i + 1) < $n && $this->isMdTableSeparator($lines[$i + 1])) {

                $headerCells = $this->parseMdTableCells($line);

                $i += 2;

                $table = '<div class="mud-md-table-wrap"><table class="mud-md-table"><thead><tr>';

                foreach ($headerCells as $cell) {

                    $table .= '<th>' . $this->inlineMd($cell) . '</th>';

                }

                $table .= '</tr></thead><tbody>';

                while ($i < $n && $this->isMdTableRow($lines[$i]) && !$this->isMdTableSeparator($lines[$i])) {

                    $cells = $this->parseMdTableCells($lines[$i]);

                    $table .= '<tr>';

                    foreach ($headerCells as $idx => $_) {

                        $table .= '<td>' . $this->inlineMd($cells[$idx] ?? '') . '</td>';

                    }

                    $table .= '</tr>';

                    $i++;

                }

                $table .= '</tbody></table></div>';

                $out[] = $table;

                $i--;

                continue;

            }

            $out[] = '<p>' . $this->inlineMd($line) . '</p>';

        }

        if ($inUl) {

            $out[] = '</ul>';

        }

        return implode("\n", $out);

    }



    private function inlineMd(string $text): string

    {

        $text = $this->esc($text);

        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text) ?? $text;

        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text) ?? $text;

        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;

        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text) ?? $text;

        return $text;

    }



    /** @param array<string, string> $attrs */
    private function renderCommentzEmbed(array $attrs): string
    {
        $pageId = (string) ($attrs['page-id'] ?? $attrs['page_id'] ?? $attrs['pageId'] ?? '/feed');
        $title = (string) ($attrs['title'] ?? 'Your feed');
        $dek = (string) ($attrs['dek'] ?? $attrs['description'] ?? 'Commentz-powered wall — flat-file posts, zero Zuckerberg.');
        $variant = (string) ($attrs['variant'] ?? 'feed');

        return '<section class="mud-commentz-wrap mud-commentz-wrap--' . $this->esc($variant) . '">'
            . '<div class="mud-commentz" id="comments" data-mud-commentz data-page-id="' . $this->esc($pageId)
            . '" data-api="' . $this->esc($this->commentApiBase) . '">'
            . '<header class="mud-commentz-header">'
            . '<p class="eyebrow">GravMUD Commentz™ · Feed</p>'
            . '<h2 class="mud-commentz-title">' . $this->esc($title) . '</h2>'
            . '<p class="mud-commentz-dek">' . $this->inlineMd($dek) . '</p>'
            . '<p class="mud-commentz-count" aria-live="polite">Loading posts…</p>'
            . '</header>'
            . '<ol class="mud-commentz-list" aria-label="Feed posts"></ol>'
            . '<form class="mud-commentz-form" autocomplete="off">'
            . '<h3 class="mud-commentz-form-title">What\'s on your mind?</h3>'
            . '<label class="mud-commentz-field"><span>Callsign</span>'
            . '<input type="text" name="name" maxlength="80" required placeholder="Your name…" /></label>'
            . '<label class="mud-commentz-field"><span>Status</span>'
            . '<textarea name="body" rows="4" maxlength="4000" required placeholder="Share something…"></textarea></label>'
            . '<label class="mud-commentz-honey" aria-hidden="true"><span>Website</span>'
            . '<input type="text" name="website" tabindex="-1" autocomplete="off" /></label>'
            . '<div class="mud-commentz-actions">'
            . '<button type="submit" class="btn primary">Post →</button>'
            . '<p class="mud-commentz-status" role="status"></p>'
            . '</div></form></div>'
            . '</section>';
    }

    /** @param array<string, string> $attrs */
    private function renderMessengerEmbed(array $attrs): string
    {
        $group = (string) ($attrs['group'] ?? $attrs['room'] ?? 'general');
        $title = (string) ($attrs['title'] ?? 'Community chat');
        $giphy = (string) ($attrs['giphy'] ?? '1');

        return '<section class="mud-messenger-wrap">'
            . '<header class="mud-messenger-wrap-head"><h2>' . $this->esc($title) . '</h2></header>'
            . '<div class="mud-messenger-embed" data-mud-messenger-embed data-group="' . $this->esc($group)
            . '" data-api="' . $this->esc($this->messengerApiBase) . '" data-giphy="' . $this->esc($giphy) . '"></div>'
            . '</section>';
    }

    /** @param array<string, string> $attrs */
    private function renderMarketplaceEmbed(array $attrs): string
    {
        $route = trim((string) ($attrs['route'] ?? $attrs['prefix'] ?? $this->marketplaceRoute), '/');
        $title = (string) ($attrs['title'] ?? 'Shop');
        $caption = (string) ($attrs['caption'] ?? 'Powered by GravMUD Marketplace plugin.');
        $height = (int) ($attrs['height'] ?? 720);
        if ($height < 320) {
            $height = 320;
        }
        $src = '/' . $route;

        return '<section class="mud-marketplace-wrap">'
            . '<header class="mud-marketplace-head">'
            . '<h2 class="mud-marketplace-title">' . $this->esc($title) . '</h2>'
            . ($caption !== '' ? '<p class="mud-marketplace-caption">' . $this->inlineMd($caption) . '</p>' : '')
            . '<p class="mud-marketplace-open"><a href="' . $this->esc($src) . '" target="_blank" rel="noopener">Open full storefront →</a></p>'
            . '</header>'
            . '<iframe class="mud-marketplace-embed" src="' . $this->esc($src) . '" title="' . $this->esc($title) . '" loading="lazy" style="min-height:' . $height . 'px"></iframe>'
            . '</section>';
    }

    /** @param array<string, string> $attrs */
    private function renderFlipzineEmbed(array $attrs): string
    {
        $base = rtrim(trim((string) ($attrs['base'] ?? $attrs['url'] ?? $this->flipzineBase)), '/');
        if ($base === '') {
            $base = 'https://flipzine.live';
        }
        $slug = trim((string) ($attrs['slug'] ?? $attrs['issue'] ?? $this->flipzineDefaultSlug));
        if ($slug === '') {
            $slug = 'gwenbot-guide';
        }
        $title = (string) ($attrs['title'] ?? 'FlipZine reader');
        $caption = (string) ($attrs['caption'] ?? 'Powered by **FlipZine.Live** — PDF in, page-flip out.');
        $height = (int) ($attrs['height'] ?? 640);
        if ($height < 320) {
            $height = 320;
        }
        $embedSrc = $base . '/embed/' . rawurlencode($slug);
        $readSrc = $base . '/read/' . rawurlencode($slug);

        return '<section class="mud-flipzine-wrap">'
            . '<header class="mud-flipzine-head">'
            . '<h2 class="mud-flipzine-title">' . $this->esc($title) . '</h2>'
            . ($caption !== '' ? '<p class="mud-flipzine-caption">' . $this->inlineMd($caption) . '</p>' : '')
            . '<p class="mud-flipzine-open"><a href="' . $this->esc($readSrc) . '" target="_blank" rel="noopener">Open full reader →</a></p>'
            . '</header>'
            . '<iframe class="mud-flipzine-embed" src="' . $this->esc($embedSrc) . '" title="' . $this->esc($title) . '" loading="lazy" allow="autoplay" style="min-height:' . $height . 'px"></iframe>'
            . '</section>';
    }

    /** @param array<string, string> $attrs */
    private function renderSwagEmbed(array $attrs): string
    {
        $route = trim((string) ($attrs['route'] ?? $attrs['prefix'] ?? $this->swagRoute), '/');
        $route = preg_replace('#/embed$#', '', $route);
        $title = (string) ($attrs['title'] ?? 'Merch shop');
        $caption = (string) ($attrs['caption'] ?? 'Powered by **GravMUD Swag Store** + Printify Pop-Up Store.');
        $height = (int) ($attrs['height'] ?? 720);
        if ($height < 320) {
            $height = 320;
        }
        $shopUrl = '/' . $route;
        $embedSrc = $shopUrl . '/embed?embed=1';

        return '<section class="mud-swag-wrap">'
            . '<header class="mud-swag-head">'
            . '<h2 class="mud-swag-title">' . $this->esc($title) . '</h2>'
            . ($caption !== '' ? '<p class="mud-swag-caption">' . $this->inlineMd($caption) . '</p>' : '')
            . '<p class="mud-swag-open"><a href="' . $this->esc($shopUrl) . '" target="_blank" rel="noopener">Open full shop page →</a></p>'
            . '</header>'
            . '<iframe class="mud-swag-embed" src="' . $this->esc($embedSrc) . '" title="' . $this->esc($title) . '" loading="lazy" style="min-height:' . $height . 'px"></iframe>'
            . '</section>';
    }



    /** @param array<string, string> $attrs @param array<string, string|list<string>> $data */
    private function renderTicker(array $attrs, array $data, string $body): string
    {
        $speed = (int) ($attrs['speed'] ?? $data['speed'] ?? 30);
        if ($speed < 10) {
            $speed = 10;
        }
        if ($speed > 120) {
            $speed = 120;
        }

        $items = [];
        foreach (preg_split('/\R/', trim($body)) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || $line === '---') {
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)/', $line, $m)) {
                $items[] = trim($m[1]);
            } elseif (!str_contains($line, ':')) {
                $items[] = $line;
            }
        }

        if ($items === [] && isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $items[] = trim($item);
                }
            }
        }

        if ($items === []) {
            return '';
        }

        $track = '';
        foreach ([0, 1] as $_dup) {
            foreach ($items as $item) {
                $track .= '<span>' . $this->inlineMd($item) . '</span><span aria-hidden="true">·</span>';
            }
        }

        return '<div class="mud-ticker-wrap" aria-hidden="true">'
            . '<div class="mud-ticker" style="animation-duration:' . $speed . 's">'
            . $track
            . '</div></div>';
    }



    private function esc(string $s): string

    {

        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    }

}


