<?php
/**
 * core/Parser.php
 *
 * Markdown- och wikisyntax-parser. Hanterar:
 *
 *   # rubrik, **fet**, *kursiv*, `kod`, ```kodblock```, listor, citat
 *
 *   [[namespace:page]]             DokuWiki-stil wikilänk
 *   [[namespace/page]]             slash-stil (normaliseras till kolon)
 *   [[namespace:page|Etikett]]     wikilänk med etikett
 *   [[wp>Sidnamn]]                 interwiki-länk (config/interwiki.php)
 *   [[https://example.com]]        absolut URL i wiki-syntax
 *
 *   [text](/namespace/page)        intern Markdown-länk (kollar om sidan finns)
 *   [text](namespace:page)         intern Markdown-länk med kolon-ID
 *   [text](https://example.com)    extern Markdown-länk
 *
 *   {{namespace:bild.png}}         media-embed
 *   {{namespace:bild.png|Alt}}
 *
 * Wikilänkar till sidor som inte finns pekar mot ?do=edit och får
 * CSS-klassen "wikilink-new" (röd, streckad) — precis som DokuWiki.
 */

class Parser
{
    public const VERSION = '6';
    private const CALLOUTS = [
        'simple' => '', 'info' => 'Information', 'note' => 'Notering',
        'tip' => 'Tips', 'important' => 'Viktigt', 'warning' => 'Varning',
        'danger' => 'Varning', 'caution' => 'Varning', 'help' => 'Hjälp',
        'download' => 'Nedladdning', 'todo' => 'Att göra', 'success' => 'Klart',
    ];
    private array $interwiki;
    private ?PageLoader $pageLoader;
    private ?PluginManager $plugins;
    private array $codeBlocks = [];

    public function __construct(array $interwiki = [], ?PageLoader $pageLoader = null, ?PluginManager $plugins = null, private ?Closure $canReadPage = null)
    {
        $this->interwiki  = $interwiki;
        $this->pageLoader = $pageLoader;
        $this->plugins    = $plugins;
    }

    public function toHtml(string $markdown): string
    {
        $this->codeBlocks = [];

        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $markdown = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $markdown);
        $markdown = $this->extractFencedCode($markdown);

        $result = $this->renderBlocks($markdown);
        $result = $this->restoreCodeBlocks($result);

        if ($this->plugins) {
            $ctx    = $this->plugins->trigger('after_parse', ['html' => $result, 'markdown' => $markdown]);
            $result = $ctx['html'] ?? $result;
        }

        return $result;
    }

    // ── Block-nivå ────────────────────────────────────────────────────────────

    private function extractFencedCode(string $md): string
    {
        return preg_replace_callback('/^ {0,3}(`{3,}|~{3,})([^\n]*)\n(.*?)\n {0,3}\1[ \t]*(?=\n|$)/ms', function ($m) {
            $token = "\x01CODEBLOCK" . count($this->codeBlocks) . "\x01";
            $lang  = Helpers::e(preg_split('/\s+/', trim($m[2]))[0]);
            $code  = Helpers::e($m[3]);
            $this->codeBlocks[] = '<pre><code' . ($lang ? ' class="language-' . $lang . '"' : '') . '>' . $code . '</code></pre>';
            return "\n\n" . $token . "\n\n";
        }, $md);
    }

    private function restoreCodeBlocks(string $html): string
    {
        foreach ($this->codeBlocks as $i => $codeHtml) {
            $html = str_replace("\x01CODEBLOCK{$i}\x01", $codeHtml, $html);
        }
        return $html;
    }

    /** Parse blocks line by line, including adjacent blocks and nested lists. */
    private function renderBlocks(string $markdown): string
    {
        $lines = explode("\n", trim($markdown, "\n"));
        $html = [];
        for ($i = 0, $count = count($lines); $i < $count;) {
            $line = $lines[$i];
            if (trim($line) === '') { $i++; continue; }
            if (preg_match('/^ {0,3}\{\{iframe:([^|{}]+)(?:\|([^|{}]*))?(?:\|(\d+))?\}\}[ \t]*$/', $line, $frame)) {
                $url = trim($frame[1]);
                $absolute = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true);
                $relative = preg_match('~^/(?!/)[^\\\\\s<>]*$~', $url);
                if (($absolute || $relative) && !preg_match('/[\x00-\x20\\\\]/', $url)) {
                    $title = trim($frame[2] ?? '') ?: 'Inbäddad sida';
                    $height = max(200, min(1600, (int) ($frame[3] ?? 600)));
                    $html[] = '<figure class="wiki-embed"><iframe src="' . Helpers::e($url) . '" title="' . Helpers::e($title)
                        . '" height="' . $height . '" loading="lazy" sandbox="allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox" referrerpolicy="strict-origin-when-cross-origin"></iframe>'
                        . '<figcaption><a href="' . Helpers::e($url) . '" target="_blank" rel="noopener noreferrer" data-new-tab="true">'
                        . Helpers::e($title) . ' — öppna i ny flik ↗</a></figcaption></figure>';
                    $i++; continue;
                }
            }
            if (preg_match('/^\x01CODEBLOCK\d+\x01$/', trim($line))) {
                $html[] = trim($line); $i++; continue;
            }
            $callout = $this->calloutOpening($line);
            if ($callout !== null) {
                $depth = 1;
                for ($end = $i + 1; $end < $count; $end++) {
                    if ($this->calloutOpening($lines[$end]) !== null) $depth++;
                    elseif (preg_match('/^ {0,3}:::[ \t]*$/', $lines[$end]) && --$depth === 0) break;
                }
                // Leave an unclosed container as text instead of swallowing the page.
                if ($end < $count) {
                    $html[] = $this->renderCallout($callout['type'], $callout['title'], implode("\n", array_slice($lines, $i + 1, $end - $i - 1)));
                    $i = $end + 1;
                    continue;
                }
            }
            if (preg_match('/^ {0,3}(#{1,6})\s+(.+?)\s*#*$/', $line, $m)) {
                $level = strlen($m[1]);
                $html[] = "<h{$level}>" . $this->inline($m[2]) . "</h{$level}>";
                $i++; continue;
            }
            if (preg_match('/^ {0,3}(?:(?:\*\s*){3,}|(?:-\s*){3,}|(?:_\s*){3,})$/', $line)) {
                $html[] = '<hr>'; $i++; continue;
            }
            if (isset($lines[$i + 1]) && str_contains($line, '|') && ($align = $this->tableAlignment($lines[$i + 1])) !== null
                && count($this->tableCells($line)) === count($align)) {
                $row = function (string $text, string $tag) use ($align): string {
                    $cells = $this->tableCells($text);
                    $out = '<tr>';
                    foreach ($align as $n => $alignment) {
                        $out .= '<' . $tag . ($tag === 'th' ? ' scope="col"' : '') . ' class="md-align-' . $alignment . '">'
                            . $this->inline($cells[$n] ?? '') . '</' . $tag . '>';
                    }
                    return $out . '</tr>';
                };
                $table = '<div class="md-table-scroll" role="region" aria-label="Tabell" tabindex="0"><table><thead>' . $row($line, 'th') . '</thead><tbody>';
                $i += 2;
                while ($i < $count && trim($lines[$i]) !== '' && str_contains($lines[$i], '|')) {
                    $table .= $row($lines[$i++], 'td');
                }
                $html[] = $table . '</tbody></table></div>';
                continue;
            }
            if (preg_match('/^ {0,3}> ?/', $line)) {
                $quote = [];
                while ($i < $count && preg_match('/^ {0,3}> ?(.*)$/', $lines[$i], $m)) {
                    $quote[] = $m[1]; $i++;
                }
                if (preg_match('/^\[!([a-z]+)\](?:[ \t]+(.*))?$/i', $quote[0], $alert) && array_key_exists(strtolower($alert[1]), self::CALLOUTS)) {
                    array_shift($quote);
                    $html[] = $this->renderCallout(strtolower($alert[1]), $alert[2] ?? '', $this->extractFencedCode(implode("\n", $quote)));
                } else {
                    $html[] = '<blockquote>' . $this->renderBlocks(implode("\n", $quote)) . '</blockquote>';
                }
                continue;
            }
            if (preg_match('/^( *)([-+*]|\d+[.)])\s+(.*)$/', $line, $m)) {
                $indent = strlen($m[1]);
                $ordered = ctype_digit($m[2][0]);
                $tag = $ordered ? 'ol' : 'ul';
                $list = '<' . $tag . ($ordered && (int) $m[2] !== 1 ? ' start="' . (int) $m[2] . '"' : '') . '>';
                while ($i < $count && preg_match('/^( *)([-+*]|\d+[.)])\s+(.*)$/', $lines[$i], $m)
                    && strlen($m[1]) === $indent && ctype_digit($m[2][0]) === $ordered) {
                    $contentIndent = strpos($lines[$i], $m[3], $indent + strlen($m[2]));
                    $item = [$m[3]];
                    $i++;
                    while ($i < $count) {
                        if (trim($lines[$i]) === '') {
                            if (isset($lines[$i + 1]) && strlen($lines[$i + 1]) - strlen(ltrim($lines[$i + 1], ' ')) > $indent) {
                                $item[] = ''; $i++; continue;
                            }
                            break;
                        }
                        $leading = strlen($lines[$i]) - strlen(ltrim($lines[$i], ' '));
                        if ($leading <= $indent) break;
                        $item[] = substr($lines[$i++], min($leading, $contentIndent));
                    }
                    $task = preg_match('/^\[([ xX])\]\s+(.*)$/', $item[0], $check);
                    if ($task) $item[0] = $check[2];
                    $body = $this->renderBlocks(implode("\n", $item));
                    $body = preg_replace('/^<p>(.*?)<\/p>/s', '$1', $body);
                    $list .= '<li' . ($task ? ' class="md-task"' : '') . '>'
                        . ($task ? '<input type="checkbox" disabled aria-label="' . ($check[1] === ' ' ? 'Ej klar' : 'Klar') . '"' . ($check[1] !== ' ' ? ' checked' : '') . '> ' : '')
                        . $body . '</li>';
                }
                $html[] = $list . '</' . $tag . '>';
                continue;
            }
            if (isset($lines[$i + 1]) && preg_match('/^ {0,3}(=+|-+)\s*$/', $lines[$i + 1], $m)) {
                $level = $m[1][0] === '=' ? 1 : 2;
                $html[] = "<h{$level}>" . $this->inline($line) . "</h{$level}>";
                $i += 2; continue;
            }
            $paragraph = [$line]; $i++;
            while ($i < $count && trim($lines[$i]) !== '' && !$this->startsBlock($lines, $i)) {
                $paragraph[] = $lines[$i++];
            }
            $html[] = '<p>' . $this->inline(implode("\n", $paragraph)) . '</p>';
        }
        return implode("\n", $html);
    }

    private function startsBlock(array $lines, int $i): bool
    {
        return $this->calloutOpening($lines[$i]) !== null
            || preg_match('/^ {0,3}\{\{iframe:/', $lines[$i])
            || preg_match('/^\s*(?:#{1,6}\s|>|[-+*]\s|\d+[.)]\s|\x01CODEBLOCK|(?:-\s*){3,}$|(?:\*\s*){3,}$|(?:_\s*){3,}$)/', $lines[$i])
            || (isset($lines[$i + 1]) && ((str_contains($lines[$i], '|') && $this->tableAlignment($lines[$i + 1]) !== null)
                || preg_match('/^ {0,3}(?:=+|-+)\s*$/', $lines[$i + 1])));
    }

    private function calloutOpening(string $line): ?array
    {
        if (!preg_match('/^ {0,3}:::[ \t]*([a-z]+)(?:[ \t]+(.*))?[ \t]*$/i', $line, $match)) return null;
        $type = strtolower($match[1]);
        return array_key_exists($type, self::CALLOUTS) ? ['type' => $type, 'title' => trim($match[2] ?? '')] : null;
    }

    private function renderCallout(string $type, string $title, string $body): string
    {
        $title = $title !== '' ? $title : self::CALLOUTS[$type];
        return '<aside class="md-callout md-callout-' . $type . '">'
            . ($title !== '' ? '<p class="md-callout-title">' . $this->inline($title) . '</p>' : '')
            . '<div class="md-callout-body">' . $this->renderBlocks($body) . '</div></aside>';
    }

    private function tableCells(string $line): array
    {
        // Protect pipes in inline code, wiki labels and escaped literal pipes.
        $protected = [];
        $line = preg_replace_callback('/`+[^`]*`+|\[\[.*?\]\]|\{\{.*?\}\}|\\\\\|/', function ($m) use (&$protected) {
            $key = "\x04" . count($protected) . "\x04";
            $protected[$key] = $m[0] === '\\|' ? '|' : $m[0];
            return $key;
        }, trim($line));
        if (str_starts_with($line, '|')) $line = substr($line, 1);
        if (str_ends_with($line, '|')) $line = substr($line, 0, -1);
        return array_map(fn ($cell) => strtr(trim($cell), $protected), explode('|', $line));
    }

    private function tableAlignment(string $line): ?array
    {
        $align = [];
        foreach ($this->tableCells($line) as $cell) {
            if (!preg_match('/^:?-{3,}:?$/', $cell)) return null;
            $align[] = str_ends_with($cell, ':') ? (str_starts_with($cell, ':') ? 'center' : 'right') : 'left';
        }
        return $align ?: null;
    }

    // ── Inline-nivå ───────────────────────────────────────────────────────────

    private function inline(string $text): string
    {
        // Varje igenkänt mönster tokeniseras → säker HTML genereras direkt.
        // Återstående klartext escapes med htmlspecialchars (skyddar mot XSS).
        $safe = [];
        $tok  = function (string $html) use (&$safe): string {
            $key        = "\x03" . count($safe) . "\x03";
            $safe[$key] = $html;
            return $key;
        };

        // Inline-kod `...`
        $text = preg_replace_callback('/`([^`]+)`/', fn ($m) =>
            $tok('<code>' . Helpers::e($m[1]) . '</code>'), $text);

        $text = preg_replace_callback('/\\\\([\\\\`*_{}\[\]()#+.!|>~-])/', fn ($m) => $tok(Helpers::e($m[1])), $text);

        // Standard Markdown images use the same lightbox as wiki media embeds.
        $text = preg_replace_callback('/!\[([^\]\x03]*)\]\(([^()\s"\x03]+)(?:\s+"([^"\x03]*)")?\)/', function ($m) use ($tok) {
            $url = $m[2];
            if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) && !preg_match('#^https?://#i', $url)) return $tok(Helpers::e($m[0]));
            return $tok('<img class="gbg-lightbox-img" src="' . Helpers::e($url) . '" alt="' . Helpers::e($m[1]) . '" loading="lazy"'
                . (isset($m[3]) ? ' title="' . Helpers::e($m[3]) . '"' : '') . '>');
        }, $text);

        // Media-embed {{id}} eller {{id|Alt}}
        $text = preg_replace_callback('/\{\{([^{}|\x03]+)(?:\|([^{}\x03]+))?\}\}/', fn ($m) =>
            $tok($this->renderMedia(trim($m[1]), isset($m[2]) ? trim($m[2]) : null)), $text);

        // Wiki-/interwiki-länkar [[...]]
        $text = preg_replace_callback('/\[\[([^\[\]\x03]+)\]\]/', fn ($m) =>
            $tok($this->renderWikiLink(trim($m[1]))), $text);

        // Markdown-länk [text](url)
        $text = preg_replace_callback('/\[([^\[\]]+)\]\(([^()\s"\x03]+)(?:\s+"[^"\x03]*")?\)/', fn ($m) =>
            $tok($this->renderMarkdownLink($m[1], $m[2])), $text);

        // Tokens protect code/HTML while allowing emphasis around links and code.
        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
        $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<!\w)__(.+?)__(?!\w)/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text);
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!\w)_(?!_)(.+?)(?<!_)_(?!\w)/s', '<em>$1</em>', $text);
        // Later tokens may contain earlier ones (e.g. inline code in link labels).
        foreach (array_reverse($safe, true) as $key => $html) $text = str_replace($key, $html, $text);
        return nl2br(trim($text));
    }

    // ── Länk-renderers ────────────────────────────────────────────────────────

    /**
     * Renderar [[...]] — DokuWiki-stil wikilänk, interwiki eller absolut URL.
     */
    private function renderWikiLink(string $inner): string
    {
        $label = null;
        if (str_contains($inner, '|')) {
            [$inner, $label] = array_map('trim', explode('|', $inner, 2));
            $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Absolut URL inuti [[ ]]
        if (preg_match('#^https?://#i', $inner)) {
            $text = $label ?? $inner;
            return '<a href="' . Helpers::e($inner) . '" rel="noopener noreferrer">' . Helpers::e($text) . '</a>';
        }

        // Interwiki: shortcut>referens, t.ex. [[wp>Göteborg]]
        if (preg_match('/^([a-zA-Z0-9_]+)>(.+)$/', $inner, $m)) {
            return $this->renderInterwikiLink($m[1], $m[2], $label);
        }

        // Normalisera snedstreck → kolon ([[namespace/page]] → namespace:page)
        $inner = str_replace('/', ':', trim($inner, '/:'));

        try {
            $pageId = new PageId($inner);
        } catch (\Throwable) {
            return Helpers::e($inner);
        }

        return $this->renderPageLink($pageId, $label);
    }

    /**
     * Renderar [text](url) — extern länk, intern wiki-länk eller ankare.
     */
    private function renderMarkdownLink(string $text, string $href): string
    {
        // Ankarlänk
        if (str_starts_with($href, '#')) {
            return '<a href="' . Helpers::e($href) . '">' . Helpers::e($text) . '</a>';
        }

        // Extern URL
        if (preg_match('#^(https?|ftp|mailto):#i', $href) || str_starts_with($href, '//')) {
            return '<a href="' . Helpers::e($href) . '" rel="noopener noreferrer">' . Helpers::e($text) . '</a>';
        }

        // Intern länk → försök mappa till wiki-sida
        $pageId = $this->hrefToPageId($href);
        if ($pageId) {
            return $this->renderPageLink($pageId, $text);
        }

        // Övriga relativa sökvägar
        return '<a href="' . Helpers::e($href) . '">' . Helpers::e($text) . '</a>';
    }

    /**
     * Gemensam renderer: slår upp om sidan finns och väljer rätt klass/URL.
     */
    private function renderPageLink(PageId $pageId, ?string $label): string
    {
        $exists = $this->pageLoader ? $this->pageLoader->exists($pageId) : true;
        $class  = $exists ? 'wikilink-exists' : 'wikilink-new';
        $href   = $exists ? $pageId->url() : $pageId->editUrl();
        $text   = $label ?? $pageId->title();
        if ($label === null && $exists && $this->pageLoader && (!$this->canReadPage || ($this->canReadPage)($pageId))) {
            $text = $this->pageLoader->load($pageId)['title'] ?? $text;
        }
        $title  = $exists ? $pageId->id() : $pageId->id() . ' (skapa sida)';

        return '<a class="' . $class . '" href="' . Helpers::e($href) . '" title="' . Helpers::e($title) . '">'
            . Helpers::e($text) . '</a>';
    }

    /**
     * Konverterar en href till PageId om det ser ut som en intern wiki-sida.
     * /namespace/page  → namespace:page
     * namespace:page   → namespace:page
     * page             → page
     */
    private function hrefToPageId(string $href): ?PageId
    {
        // /namespace/page
        if (str_starts_with($href, '/') && strlen($href) > 1) {
            $path = trim(rawurldecode($href), '/');
            try { return new PageId(str_replace('/', ':', $path)); } catch (\Throwable) { return null; }
        }

        // namespace:page eller enstaka sidnamn (inga snedstreck, inget protokoll)
        if (!str_contains($href, '/') && preg_match('/^[\pL\pN][\pL\pN_\-.:]*$/u', $href)) {
            try { return new PageId($href); } catch (\Throwable) { return null; }
        }

        return null;
    }

    private function renderInterwikiLink(string $shortcut, string $reference, ?string $label): string
    {
        if (!isset($this->interwiki[$shortcut])) {
            return Helpers::e("[[{$shortcut}>{$reference}]]");
        }
        $href = str_replace('%s', rawurlencode(str_replace(' ', '_', $reference)), $this->interwiki[$shortcut]);
        $text = $label ?? ($shortcut . ':' . $reference);

        return '<a class="interwiki interwiki-' . Helpers::e($shortcut) . '" href="' . Helpers::e($href)
            . '" rel="nofollow noopener" title="' . Helpers::e($href) . '">' . Helpers::e($text) . '</a>';
    }

    private function renderMedia(string $id, ?string $alt): string
    {
        try {
            $mediaId = new MediaId($id);
        } catch (\Throwable) {
            return Helpers::e("{{{$id}}}");
        }

        $altText = $alt ?? $mediaId->filename();

        if ($mediaId->isImage()) {
            // .gbg-lightbox-img styr theme.js/style.css lightbox (se
            // assets/js/theme.js) — bara embeddade bilder ska öppnas i en
            // lightbox vid klick, inte t.ex. header-loggan.
            return '<img class="gbg-lightbox-img" src="' . Helpers::e($mediaId->url()) . '" alt="' . Helpers::e($altText) . '" loading="lazy">';
        }

        return '<a class="media-download" href="' . Helpers::e($mediaId->url()) . '">' . Helpers::e($altText) . '</a>';
    }
}
