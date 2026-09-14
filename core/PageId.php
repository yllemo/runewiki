<?php
/**
 * core/PageId.php
 *
 * Värdesobjekt för ett sid-ID, t.ex. "namespace:page1:start".
 *
 * Filmappningsregel:
 *   - Den FÖRSTA kolon-separerade delen blir en mapp under /content
 *     (namespacet).
 *   - Alla ÖVRIGA delar slås ihop med punkt "." till själva filnamnet.
 *
 *   "start"                     -> content/start.md
 *   "projekt:api"                -> content/projekt/api.md
 *   "namespace:page1:start"      -> content/namespace/page1.start.md
 *   "a:b:c:d"                    -> content/a/b.c.d.md
 *
 * Detta skiljer sig medvetet från "ren" DokuWiki (där varje kolon blir
 * en egen undermapp) — här får varje toppnivå-namespace EN mapp, och
 * djupare hierarki uttrycks som punktseparerade filnamn i den mappen.
 * Det håller mappdjupet lågt samtidigt som ID-strukturen känns igen.
 */

class PageId
{
    private string $id;
    private array $parts;

    public function __construct(string $id)
    {
        $id = str_replace(['/', '\\'], ':', $id);
        $id = trim($id, ": \t\n\r\0\x0B");
        $id = $id === '' ? 'start' : $id;

        $parts = array_values(array_filter(
            explode(':', $id),
            fn ($p) => $p !== ''
        ));

        // Use identical ASCII names on Linux and Windows, independent of locale
        // and optional PHP extensions. Apply the same rule to namespaces/pages.
        $this->parts = array_values(array_filter(
            array_map([self::class, 'normalizeSegment'], $parts),
            fn ($p) => $p !== ''
        ));

        if (empty($this->parts)) {
            $this->parts = ['start'];
        }
        $this->id = implode(':', $this->parts);
    }

    private static function normalizeSegment(string $part): string
    {
        $original = mb_strtolower(trim($part), 'UTF-8');
        // These exact names are intentional wiki control files.
        if (in_array($original, ['_sidebar', '_topbar'], true)) return $original;
        $part = strtr($original, [
            'å' => 'a', 'ä' => 'a', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ā' => 'a',
            'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
            'ü' => 'u', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ū' => 'u',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ń' => 'n', 'ç' => 'c', 'č' => 'c', 'ć' => 'c',
            'š' => 's', 'ś' => 's', 'ž' => 'z', 'ź' => 'z', 'ż' => 'z', 'ł' => 'l',
            'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', 'ð' => 'd', 'þ' => 'th',
        ]);
        // Decomposed accents (e.g. a + combining diaeresis) normalize too.
        $part = preg_replace('/\p{M}+/u', '', $part);
        $part = preg_replace('/[^a-z0-9_-]+/', '_', $part);
        $part = trim(preg_replace('/_+/', '_', $part), '_-');
        // Never turn an all-symbol/non-Latin name into the existing start page.
        if ($part === '') $part = 'sida-' . substr(hash('sha256', $original), 0, 16);
        // Avoid Windows device names when the same repository is used locally.
        if (preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])$/', $part)) $part = 'sida-' . $part;
        return $part;
    }

    public static function fromRelativePath(string $relativePath, string $contentDir): self
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $contentDir = str_replace('\\', '/', $contentDir);
        $relativePath = preg_replace('#^' . preg_quote(rtrim($contentDir, '/'), '#') . '/#', '', $relativePath);
        $relativePath = preg_replace('/\.md$/', '', $relativePath);

        $segments = explode('/', $relativePath);
        $last = array_pop($segments); // "page1.start" eller "start"
        $lastParts = $last === '' ? [] : explode('.', $last);

        $idParts = array_merge($segments, $lastParts);

        return new self(implode(':', $idParts));
    }

    public function id(): string
    {
        return $this->id;
    }

    /** Namespace = den första delen av ID:t (motsvarar mappnamnet). */
    public function namespace(): string
    {
        return count($this->parts) > 1 ? $this->parts[0] : '';
    }

    /** De delar som slås ihop till filnamnet (allt utom namespace-delen). */
    public function nameParts(): array
    {
        return count($this->parts) > 1 ? array_slice($this->parts, 1) : $this->parts;
    }

    public function toFilePath(string $contentDir): string
    {
        $contentDir = rtrim($contentDir, '/');

        if (count($this->parts) === 1) {
            return $contentDir . '/' . $this->parts[0] . '.md';
        }

        $namespace = $this->parts[0];
        $filename  = implode('.', array_slice($this->parts, 1)) . '.md';

        return $contentDir . '/' . $namespace . '/' . $filename;
    }

    /** Sidopanel för namespacet: content/<namespace>/_sidebar.md (fallback: content/_sidebar.md) */
    public function sidebarPath(string $contentDir): string
    {
        $contentDir = rtrim($contentDir, '/');
        $ns = $this->namespace();
        return $ns === '' ? $contentDir . '/_sidebar.md' : $contentDir . '/' . $ns . '/_sidebar.md';
    }

    public function url(): string
    {
        return '/' . str_replace(':', '/', $this->id);
    }

    public function editUrl(): string
    {
        return '/' . str_replace(':', '/', $this->id) . '?do=edit';
    }

    public function title(): string
    {
        $parts = $this->nameParts();
        $last = end($parts);
        return ucfirst(str_replace(['_', '-'], ' ', $last));
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
