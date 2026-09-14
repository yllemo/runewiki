<?php
/** Request-local reverse index built from the same HTML as the wiki renderer. */
class References
{
    private ?array $index = null;

    public function __construct(private PageLoader $pages, private array $interwiki = []) {}

    public function backlinks(PageId $id): array
    {
        $this->build();
        return array_values(array_filter($this->index['pages'][$id->id()] ?? [], fn ($source) => $source['id'] !== $id->id()));
    }

    public function imageReferences(MediaId $id): array
    {
        $this->build();
        return array_values($this->index['images'][rawurldecode($id->url())] ?? []);
    }

    private function build(): void
    {
        if ($this->index !== null) return;
        $this->index = ['pages' => [], 'images' => []];
        $parser = new Parser($this->interwiki);
        foreach ($this->pages->listAll(true) as $sourceId) {
            $id = new PageId($sourceId);
            $page = $this->pages->load($id);
            if (!$page) continue;
            $source = ['id' => $id->id(), 'title' => (string) ($page['meta']['title'] ?? $id->title()), 'url' => $id->url()];
            // Code examples are escaped by Parser, so they cannot become references.
            $html = $parser->toHtml($page['body']);
            preg_match_all('/<(a|img)\b[^>]*\b(?:href|src)="([^"]*)"[^>]*>/i', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($href === '' || $href[0] === '#') continue;
                $url = parse_url($href);
                if ($url === false) continue;
                if (isset($url['scheme']) && !in_array(strtolower($url['scheme']), ['http', 'https'], true)) continue;
                if (isset($url['host'])) {
                    $authority = strtolower($url['host']) . (isset($url['port']) ? ':' . $url['port'] : '');
                    if ($authority !== strtolower($_SERVER['HTTP_HOST'] ?? '')) continue;
                }
                $path = rawurldecode($url['path'] ?? '/');
                if (str_starts_with($path, '/images/')) {
                    $this->index['images'][$path][$sourceId] = $source;
                } elseif (strtolower($match[1]) === 'a' && str_starts_with($path, '/') && !preg_match('#^/(admin|chat)(/|$)#', $path)) {
                    parse_str($url['query'] ?? '', $query);
                    if (isset($query['do']) && !in_array($query['do'], ['view', 'edit'], true)) continue;
                    $target = new PageId(trim($path, '/'));
                    $this->index['pages'][$target->id()][$sourceId] = $source;
                }
            }
        }
    }
}
