<?php



namespace Grav\Plugin\GravMudAlpha;



/**

 * Grav MUD Alpha — MarkUpDown (.mud) compiler (PHP).

 * v0.3: Design Spec fences, @@@ tokens, Lone Mamber layout primitives.

 */

class MudAlphaCompiler

{

    private ?MudDesignSpec $design = null;

    private bool $designOpen = false;

    private string $assetBase = '';

    private string $forumApiBase = '/api/mud-forumz';



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

    public function setForumApiBase(string $base): void
    {
        $this->forumApiBase = '/' . trim($base, '/');
    }

    /** Compile a snippet for spec examples (nested fences). */
    public function compileSnippet(string $source): string
    {
        $compiler = new self();
        if ($this->assetBase !== '') {
            $compiler->setAssetBase($this->assetBase);
        }
        if ($this->forumApiBase !== '/api/mud-forumz') {
            $compiler->setForumApiBase($this->forumApiBase);
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



            if (preg_match('/^:::\s+/', $trim)) {

                $flushMd();

                $header = $this->parseFenceHeader($line);

                $i++;

                $body = [];

                $fenceDepth = 1;

                while ($i < $count) {

                    $innerTrim = trim($lines[$i]);

                    if (preg_match('/^:::\s+\S/', $innerTrim)) {

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

            || (bool) preg_match('/^:::\s/', $t)

            || (bool) preg_match('/^@@@/', $t)

            || (bool) preg_match('/^#{1,6}\s+/', $t);

    }



    /** @return array{fenceType: string, attrs: array<string, string>} */

    private function parseFenceHeader(string $line): array

    {

        $inner = trim(preg_replace('/^:::\s+/', '', $line) ?? '');

        $parts = preg_split('/\s+/', $inner) ?: [];

        $fenceType = strtolower($parts[0] ?? 'unknown');

        $attrs = [];

        for ($j = 1, $n = count($parts); $j < $n; $j++) {

            $token = $parts[$j];

            if (strpos($token, '=') !== false) {

                [$k, $v] = explode('=', $token, 2);

                $attrs[$k] = trim($v, "\"'");

            } elseif (!isset($attrs['variant'])) {

                $attrs['variant'] = $token;

            }

        }

        return ['fenceType' => $fenceType, 'attrs' => $attrs];

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

            'guestbook', 'spec-guestbook', 'cards', 'spec-cards',

            'timeline', 'spec-timeline', 'compare', 'spec-compare',

            'receipts', 'spec-receipts', 'manifesto', 'spec-manifesto',

            'blog-teaser', 'spec-blog-teaser', 'blog-index', 'spec-blog-index',

            'blog-post-header', 'spec-blog-post-header', 'blog-body', 'spec-blog-body',

            'pricing', 'spec-pricing', 'code', 'spec-code',

            'video', 'spec-video',

            'theme', 'spec-theme',

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



            case 'section':

                $id = !empty($attrs['id']) ? ' id="' . $this->esc($attrs['id']) . '"' : '';

                $inner = $children

                    ? $this->renderNodes($children)

                    : $this->renderMarkdown(trim($body));

                return '<section class="mud-section"' . $id . '>' . $inner . '</section>';



            case 'card':

                return '<article class="mud-card">'

                    . (!empty($data['title']) ? '<h3>' . $this->inlineMd((string) $data['title']) . '</h3>' : '')

                    . (!empty($data['body']) ? '<div class="mud-card-body">' . $this->renderMarkdown((string) $data['body']) . '</div>' : '')

                    . '</article>';



            case 'forum':

                $board = (string) ($attrs['board'] ?? 'general');

                $limit = (string) ($attrs['limit'] ?? '20');

                return '<section class="mud-forumz-wrap"><div class="mud-forumz" data-mud-forumz data-mode="board" data-board="'

                    . $this->esc($board) . '" data-limit="' . $this->esc($limit)

                    . '" data-api="' . $this->esc($this->forumApiBase) . '"><p class="mud-forumz-loading">Loading Forumz…</p></div></section>';



            case 'forum-thread':

                $board = (string) ($attrs['board'] ?? 'general');

                $thread = (string) ($attrs['thread'] ?? '');

                return '<section class="mud-forumz-wrap"><div class="mud-forumz" data-mud-forumz data-mode="thread" data-board="'

                    . $this->esc($board) . '" data-thread="' . $this->esc($thread)

                    . '" data-api="' . $this->esc($this->forumApiBase) . '"><p class="mud-forumz-loading">Loading thread…</p></div></section>';



            case 'forum-profile':

                $user = (string) ($attrs['user'] ?? $attrs['slug'] ?? '');

                return '<section class="mud-forumz-wrap"><div class="mud-forumz" data-mud-forumz data-mode="profile" data-user="'

                    . $this->esc($user) . '" data-api="' . $this->esc($this->forumApiBase) . '"><p class="mud-forumz-loading">Loading profile…</p></div></section>';



            case 'forum-profiles':

                $limit = (string) ($attrs['limit'] ?? '12');

                return '<section class="mud-forumz-wrap"><div class="mud-forumz" data-mud-forumz data-mode="profiles" data-limit="'

                    . $this->esc($limit) . '" data-api="' . $this->esc($this->forumApiBase) . '"><p class="mud-forumz-loading">Loading gravvers…</p></div></section>';



            default:

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



    private function renderMarkdown(string $content): string

    {

        if ($content === '') {

            return '';

        }

        $out = [];

        $inUl = false;

        foreach (preg_split('/\r?\n/', $content) as $line) {

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



    private function esc(string $s): string

    {

        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    }

}


