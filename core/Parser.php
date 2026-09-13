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
    private array $interwiki;
    private ?PageLoader $pageLoader;
    private ?PluginManager $plugins;
    private array $codeBlocks = [];

    public function __construct(array $interwiki = [], ?PageLoader $pageLoader = null, ?PluginManager $plugins = null)
    {
        $this->interwiki  = $interwiki;
        $this->pageLoader = $pageLoader;
        $this->plugins    = $plugins;
    }

    public function toHtml(string $markdown): string
    {
        $this->codeBlocks = [];

        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $markdown = $this->extractFencedCode($markdown);

        $blocks = preg_split('/\n{2,}/', trim($markdown));
        $html   = [];

        foreach ($blocks as $block) {
            $html[] = $this->renderBlock($block);
        }

        $result = implode("\n", $html);
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
        return preg_replace_callback('/```(\w*)\n(.*?)\n```/s', function ($m) {
            $token = "\x01CODEBLOCK" . count($this->codeBlocks) . "\x01";
            $lang  = Helpers::e($m[1]);
            $code  = Helpers::e($m[2]);
            $this->codeBlocks[] = '<pre><code' . ($lang ? ' class="language-' . $lang . '"' : '') . '>' . $code . '</code></pre>';
            return $token;
        }, $md);
    }

    private function restoreCodeBlocks(string $html): string
    {
        foreach ($this->codeBlocks as $i => $codeHtml) {
            $html = str_replace("\x01CODEBLOCK{$i}\x01", $codeHtml, $html);
        }
        return $html;
    }

    private function renderBlock(string $block): string
    {
        if (str_starts_with(trim($block), "\x01CODEBLOCK")) {
            return trim($block);
        }

        if (preg_match('/^(#{1,6})\s+(.*)$/', $block, $m)) {
            $level = strlen($m[1]);
            return "<h{$level}>" . $this->inline(trim($m[2])) . "</h{$level}>";
        }

        if (preg_match('/^---+$/', trim($block))) {
            return '<hr>';
        }

        if (preg_match('/^>\s?/m', $block)) {
            $lines = array_map(fn ($l) => preg_replace('/^>\s?/', '', $l), explode("\n", $block));
            return '<blockquote><p>' . $this->inline(implode(' ', $lines)) . '</p></blockquote>';
        }

        $lines = explode("\n", $block);

        if (preg_match('/^\s*[-*]\s+/', $lines[0])) {
            $items = array_map(fn ($l) => '<li>' . $this->inline(preg_replace('/^\s*[-*]\s+/', '', $l)) . '</li>', $lines);
            return '<ul>' . implode('', $items) . '</ul>';
        }

        if (preg_match('/^\s*\d+\.\s+/', $lines[0])) {
            $items = array_map(fn ($l) => '<li>' . $this->inline(preg_replace('/^\s*\d+\.\s+/', '', $l)) . '</li>', $lines);
            return '<ol>' . implode('', $items) . '</ol>';
        }

        return '<p>' . $this->inline($block) . '</p>';
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

        // Media-embed {{id}} eller {{id|Alt}}
        $text = preg_replace_callback('/\{\{([^{}|]+)(?:\|([^{}]+))?\}\}/', fn ($m) =>
            $tok($this->renderMedia(trim($m[1]), isset($m[2]) ? trim($m[2]) : null)), $text);

        // Wiki-/interwiki-länkar [[...]]
        $text = preg_replace_callback('/\[\[([^\[\]]+)\]\]/', fn ($m) =>
            $tok($this->renderWikiLink(trim($m[1]))), $text);

        // Markdown-länk [text](url)
        $text = preg_replace_callback('/\[([^\[\]]+)\]\(([^()\s"]+)(?:\s+"[^"]*")?\)/', fn ($m) =>
            $tok($this->renderMarkdownLink($m[1], $m[2])), $text);

        // Dela upp på tokens; escape klartext, applicera fetstil/kursiv
        $parts = preg_split('/(\x03\d+\x03)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $text  = implode('', array_map(function (string $part) use ($safe): string {
            if (array_key_exists($part, $safe)) {
                return $part; // platshållare — återställs i strtr() nedan
            }
            // Escape råa HTML-taggar från användarinnehåll
            $part = htmlspecialchars($part, ENT_NOQUOTES, 'UTF-8');
            $part = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $part);
            $part = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $part);
            return $part;
        }, $parts));

        return nl2br(strtr(trim($text), $safe));
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
            $path = trim($href, '/');
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
