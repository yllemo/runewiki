<?php
/**
 * core/Search.php
 *
 * Enkel filbaserad fulltextsökning över content/. Söker direkt i
 * filerna vid varje anrop (inget separat index — en större wiki kan
 * byta ut denna mot en indexbaserad implementation utan att API:t ändras).
 */

class Search
{
    public function __construct(private string $contentDir)
    {
    }

    /** @return array<int, array{id:string, title:string, excerpt:string, url:string}> */
    public function query(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $results = [];
        $loader  = new PageLoader($this->contentDir);

        foreach ($loader->listAll() as $id) {
            $pageId = new PageId($id);
            $page   = $loader->load($pageId);
            if (!$page) {
                continue;
            }
            $haystack = ($page['meta']['title'] ?? '') . "\n" . $page['body'];
            if (mb_stripos($haystack, $term) === false) {
                continue;
            }
            $results[] = [
                'id'      => $id,
                'title'   => $page['meta']['title'] ?? $pageId->title(),
                'excerpt' => $this->excerpt($page['body'], $term),
                'url'     => $pageId->url(),
            ];
        }

        return $results;
    }

    /**
     * Söker efter sidor som har en exakt tagg-matchning i frontmatter.
     * Används när söktermen är "tag:nyckelord".
     *
     * @return array<int, array{id:string, title:string, excerpt:string, url:string}>
     */
    public function queryByTag(string $tag): array
    {
        $tag = trim($tag);
        if ($tag === '') {
            return [];
        }

        $results = [];
        $loader  = new PageLoader($this->contentDir);

        foreach ($loader->listAll() as $id) {
            $pageId = new PageId($id);
            $page   = $loader->load($pageId);
            if (!$page) {
                continue;
            }
            $tags = array_map('trim', (array) ($page['meta']['tags'] ?? []));
            if (!in_array($tag, $tags, true)) {
                continue;
            }
            $excerpt = trim($page['body']);
            $results[] = [
                'id'      => $id,
                'title'   => $page['meta']['title'] ?? $pageId->title(),
                'excerpt' => mb_substr($excerpt, 0, 140) . (mb_strlen($excerpt) > 140 ? '…' : ''),
                'url'     => $pageId->url(),
            ];
        }

        return $results;
    }

    /**
     * Söker efter alla sidor i ett givet namespace (mapp under /content).
     * Tomt namespace eller "_root" matchar rotnivåns sidor (utan mapp) —
     * samma konvention som Helpers::buildPageTree(). Används när
     * söktermen är "ns:<namespace>".
     *
     * @return array<int, array{id:string, title:string, excerpt:string, url:string}>
     */
    public function queryByNamespace(string $ns): array
    {
        $ns       = trim($ns);
        $wantRoot = ($ns === '' || strcasecmp($ns, '_root') === 0);

        $results = [];
        $loader  = new PageLoader($this->contentDir);

        foreach ($loader->listAll() as $id) {
            $pageId = new PageId($id);
            $pageNs = $pageId->namespace();
            $match  = $wantRoot ? ($pageNs === '') : (strcasecmp($pageNs, $ns) === 0);
            if (!$match) {
                continue;
            }
            $page = $loader->load($pageId);
            if (!$page) {
                continue;
            }
            $excerpt = trim($page['body']);
            $results[] = [
                'id'      => $id,
                'title'   => $page['meta']['title'] ?? $pageId->title(),
                'excerpt' => mb_substr($excerpt, 0, 140) . (mb_strlen($excerpt) > 140 ? '…' : ''),
                'url'     => $pageId->url(),
            ];
        }

        return $results;
    }

    private function excerpt(string $body, string $term): string
    {
        $pos = mb_stripos($body, $term);
        if ($pos === false) {
            return mb_substr(trim($body), 0, 140) . '…';
        }
        $start = max(0, $pos - 60);
        $snippet = mb_substr($body, $start, 160);
        return ($start > 0 ? '…' : '') . trim($snippet) . '…';
    }
}
